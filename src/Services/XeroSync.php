<?php

namespace bluntelk\IxpManagerXero\Services;

use bluntelk\IxpManagerXero\Sync\SyncAction;
use Entities\Customer;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Webfox\Xero\OauthCredentialManager;
use XeroAPI\XeroPHP\Api\AccountingApi;
use XeroAPI\XeroPHP\Models\Accounting\Address;
use XeroAPI\XeroPHP\Models\Accounting\Contacts;
use XeroAPI\XeroPHP\Models\Accounting\Contact;
use XeroAPI\XeroPHP\Models\Accounting\Error;
use XeroAPI\XeroPHP\Models\Accounting\Phone;

class XeroSync
{
    /**
     * @var AccountingApi
     */
    private $xero;
    /**
     * @var OauthCredentialManager
     */
    private $xeroCredentials;

    protected $requiredScopes = [ 'accounting.contacts', 'accounting.settings.read' ];


    public function __construct(OauthCredentialManager $xeroCredentials, AccountingApi $xero) {
        $this->xero = $xero;
        $this->xeroCredentials = $xeroCredentials;
    }


    public function isXeroConfigValid(): bool
    {
        foreach( $this->requiredScopes as $scope ) {
            if( !in_array( $scope, config( 'xero.oauth.scopes' ) ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return Customer[]
     */
    protected function listIxpCustomers(): array
    {
        /** @var \Repositories\Customer $customerRepo */
        $customerRepo = \D2EM::getRepository(Customer::class );
        /** @var Customer[] $list */
        return $customerRepo->getCurrentActive();
    }

    /**
     * @return Contact[]|Contacts
     */
    protected function listXeroAccountingContacts(): Contacts
    {
        Log::info("Asking Xero for Contacts");
        /** @var Contacts $list */
        $list = $this->xero->getContacts($this->xeroCredentials->getTenantId());
        $n = count($list);
        Log::info("Fetched $n records from Xero");
        return $list;
    }

    /**
     * @return SyncAction[]
     */
    public function prepareSync(): array
    {
        if (!$this->isXeroConfigValid()) {
            Log::error("Your Xero Integration Settings are Invalid.");
            return [];
        }

        $accountingContacts = $this->listXeroAccountingContacts();

        $actions = [];
        foreach ($this->listIxpCustomers() as $customer) {
            if (!in_array($customer->getType(), config('ixpxero.sync_customer_types'))) {
                Log::debug("Ignoring Customer ({$customer->getName()} - Type: {$customer->getTypeText()})");
                continue;
            }
            // match based on ASN
            $memberAsn = 'AS' . $customer->getAutsys();
            Log::debug("Customer {$memberAsn}");
            $found = false;
            foreach ($accountingContacts as $contact) {
                if ($contact->getAccountNumber() == $memberAsn) {
                    $found=true;
                    $actions[] = new SyncAction(SyncAction::ACTION_UPDATE, $customer, $contact);
                    break;
                }
            }
            if (!$found) {
                $actions[] = new SyncAction(SyncAction::ACTION_CREATE, $customer, null);
            }
        }

        return $actions;
    }

    public function performSync()
    {
        $nullOr = function($value) {
            return $value ? $value : null;
        };
        $trimNull = function(array $arr) {
            foreach ($arr as $k => $v) {
                if (is_null($v)) {
                    unset($arr[$k]);
                }
            }
            return $arr;
        };
        $contacts = new Contacts();
        $syncActions = $this->prepareSync();
        foreach ($syncActions as $syncAction) {
            Log::info("About to perform sync action: {$syncAction}");
            $c = $syncAction->customer;

            if (!$c->getAutsys()) {
                Log::warning("Action {$syncAction} cannot be performed, Member does not have an ASN to sync against");
                continue;
            }

            switch($syncAction->action) {
                case SyncAction::ACTION_CREATE:
                case SyncAction::ACTION_UPDATE:
                    $memberAsn = 'AS' . $c->getAutsys();

                    $address_registration = new Address($trimNull([
                        'address_line1' => $nullOr($c->getRegistrationDetails()->getAddress1()),
                        'address_line2' => $nullOr($c->getRegistrationDetails()->getAddress2()),
                        'address_line3' => $nullOr($c->getRegistrationDetails()->getAddress3()),
                        'city' => $nullOr($c->getRegistrationDetails()->getTownCity()),
                        'postal_code' => $nullOr($c->getRegistrationDetails()->getPostcode()),
                        'country' => $nullOr($c->getRegistrationDetails()->getCountry()),
                    ]));

                    $address_billing = new Address($trimNull([
                        'address_line1' => $nullOr($c->getBillingDetails()->getBillingAddress1()),
                        'address_line2' => $nullOr($c->getBillingDetails()->getBillingAddress2()),
                        'address_line3' => $nullOr($c->getBillingDetails()->getBillingAddress3()),
                        'city' => $nullOr($c->getBillingDetails()->getBillingTownCity()),
                        'postal_code' => $nullOr($c->getBillingDetails()->getBillingPostcode()),
                        'country' => $nullOr($c->getBillingDetails()->getBillingCountryName()),
                        'attention_to' => $nullOr($c->getBillingDetails()->getBillingContactName()),
                    ]));
                    $addresses = [];
                    if ($address_registration->valid() && "{}" != "{$address_registration}") {
                        $addresses[] = $address_registration;
                    }
                    if ($address_billing->valid() && "{}" != "{$address_billing}") {
                        $addresses[] = $address_billing;
                    }

                    $phones = [];
                    $phone = new Phone([
                        'phone_number' => $nullOr($c->getBillingDetails()->getBillingTelephone()),
                    ]);
                    if ($phone->valid() && '{}' != "{$phone}") {
                        $phones[] = $phone;
                    }

                    $contact = new Contact([
                        'contact_number' => $syncAction->getMemberId(),
                        'is_customer' => true,
                        'name' => $c->getName(),
                        'first_name' => $nullOr($c->getBillingDetails()->getBillingContactName()),
                        'last_name' => $memberAsn,
                        'addresses' => $addresses ? $addresses : null,
                        'email_address' => $nullOr($c->getBillingDetails()->getBillingEmail()),
                        'phones' => $phones ? $phones : null,
                    ]);
                    $syncAction->performed = true;

                    if ($contact->valid()) {
                        $contacts[] = $contact;
                    } else {
                        foreach ($contact->listInvalidProperties() as $invalidProperty) {
                            Log::error("Unable to perform {$syncAction}: $invalidProperty");
                        }
                    }
//                    echo "{$contact}"; die;
                    break;
                case SyncAction::ACTION_DO_NOTHING:
                    Log::debug("Successfully did nothing");
                    break;
            }

        }

//        print_r($contacts);
        $n = count($contacts);
        Log::info("About to send $n contacts to Xero");
        try {
            /** @var Contact[] $retContacts */
            $retContacts = $this->xero->updateOrCreateContacts($this->xeroCredentials->getTenantId(), $contacts);
            foreach ($retContacts as $retContact) {
                if ($retContact->getHasValidationErrors()) {
                    foreach ($retContact->getValidationErrors() as $error) {
                        Log::error($error->getMessage());
                    }
                    foreach ($syncActions as $syncAction) {
                        if ($syncAction->getMemberId() == $retContact->getContactNumber()) {
                            $syncAction->failed = true;
                            $syncAction->errors[] = $retContact->getValidationErrors();
                            break;
                        }
                    }
                }


            }
            //print_r($result);
        } catch(\XeroAPI\XeroPHP\ApiException $e) {
            Log::error($e->getMessage());
            Log::error($e->getResponseBody());

            /** @var \XeroAPI\XeroPHP\Models\Accounting\Error $obj */
            $obj = $e->getResponseObject();
            if ($obj instanceof Error) {
                foreach ($obj->getElements() as $item) {
                    foreach ($item->getValidationErrors() as $err) {
                        Log::error($err->getMessage());
                    }
                }
            }

            throw new \Exception("Failed to Sync. {$e->getMessage()}", 1, $e);
        }
        return $syncActions;
    }
}