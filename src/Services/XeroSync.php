<?php

namespace bluntelk\IxpManagerXero\Services;

use Ixp\Models\Customer;
use bluntelk\IxpManagerXero\Sync\SyncAction;
use Illuminate\Support\Facades\Log;
use IXP\Exceptions\GeneralException;
use Psr\Log\LoggerInterface;
use Webfox\Xero\OauthCredentialManager;
use XeroAPI\XeroPHP\Api\AccountingApi;
use XeroAPI\XeroPHP\Models\Accounting\Address;
use XeroAPI\XeroPHP\Models\Accounting\ContactGroup;
use XeroAPI\XeroPHP\Models\Accounting\ContactGroups;
use XeroAPI\XeroPHP\Models\Accounting\ContactPerson;
use XeroAPI\XeroPHP\Models\Accounting\Contacts;
use XeroAPI\XeroPHP\Models\Accounting\Contact;
use XeroAPI\XeroPHP\Models\Accounting\Error;
use XeroAPI\XeroPHP\Models\Accounting\Phone;
use \IXP\Models\ContactGroup as IxpContactGroup;

class XeroSync
{
    private AccountingApi $xero;
    private OauthCredentialManager $xeroCredentials;

    private $seenAsns = [];

    protected $requiredScopes = [ 'accounting.contacts', 'accounting.settings.read' ];

    public function __construct( OauthCredentialManager $xeroCredentials, AccountingApi $xero )
    {
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
        return Customer::active();
    }

    protected function getXeroAccountingContact( Customer $customer ): ?Contact
    {
        Log::info( "Asking Xero for Contacts" );
        $memberId = $this->getMemberId( $customer );

        /** @var Contacts $list */
        $list = $this->xero->getContacts( $this->xeroCredentials->getTenantId(), null, "ContactNumber==\"{$memberId}\"" );
        $n = count( $list );
        Log::info( "Fetched $n records from Xero" );
        if( $n == 1 ) {
            return $list[ 0 ];
        }
        return null;
    }

    /**
     * @return Contact[]|Contacts
     */
    protected function listXeroAccountingContacts(): Contacts
    {
        Log::info( "Asking Xero for Contacts" );
        /** @var Contacts $list */
        $list = $this->xero->getContacts( $this->xeroCredentials->getTenantId() );
        $n = count( $list );
        Log::info( "Fetched $n records from Xero" );
        return $list;
    }

    /**
     * @return ContactGroup[]|ContactGroups
     */
    protected function listXeroAccountingContactGroups(): ContactGroups
    {
        Log::info( "Asking Xero for Contact Groups" );
        /** @var ContactGroups $list */
        $list = $this->xero->getContactGroups( $this->xeroCredentials->getTenantId() );
        $n = count( $list );
        Log::info( "Fetched $n records from Xero" );
        return $list;
    }

    /**
     * @return SyncAction[]
     */
    public function prepareSync(): array
    {
        if( !$this->isXeroConfigValid() ) {
            Log::error( "Your Xero Integration Settings are Invalid." );
            return [];
        }

        $accountingContacts = $this->listXeroAccountingContacts();

        $actions = [];
        foreach( $this->listIxpCustomers() as $customer ) {
            if( !in_array( $customer->getType(), config( 'ixpxero.sync_customer_types' ) ) ) {
                try {
                    Log::debug( "Ignoring Customer ({$customer->getName()} - Type: {$customer->getTypeText()})" );
                } catch( GeneralException $e ) {
                    Log::debug( "Ignoring Customer ({$customer->getName()} - Type: Unknown)" );
                }
                continue;
            }
            // match based on ASN
            $memberAsn = 'AS' . $customer->getAutsys();
            Log::debug( "Customer {$memberAsn}" );
            $found = false;
            foreach( $accountingContacts as $contact ) {
                if( $contact->getAccountNumber() == $memberAsn ) {
                    $found = true;
                    $actions[] = new SyncAction( SyncAction::ACTION_UPDATE, $customer, $contact );
                    break;
                }
            }
            if( !$found ) {
                $actions[] = new SyncAction( SyncAction::ACTION_CREATE, $customer, null );
            }
        }

        return $actions;
    }

    protected function getMemberId( Customer $customer ): string
    {
        return 'MemberId=' . $customer->getId();
    }

    public function performSyncAll()
    {
        return $this->performSync( $this->prepareSync() );
    }

    public function performSyncOne( Customer $customer )
    {
        $accountingContact = $this->getXeroAccountingContact( $customer );
        $syncAction = new SyncAction( SyncAction::ACTION_UPDATE, $customer, $accountingContact );
        return $this->performSync( [ $syncAction ] );
    }

    /**
     * @param SyncAction[] $syncActions
     * @return SyncAction[]
     */
    public function performSync( array $syncActions )
    {
        $contacts = new Contacts();
        foreach( $syncActions as $syncAction ) {
            Log::info( "About to perform sync action: {$syncAction}" );

            switch( $syncAction->action ) {
                case SyncAction::ACTION_CREATE:
                case SyncAction::ACTION_UPDATE:

                    $contact = $this->makeXeroContact( $syncAction );
//                    print_r($contact);

                    if( $contact->valid() ) {
                        $contacts[] = $contact;
                        $syncAction->performed = true;
                    } else {
                        foreach( $contact->listInvalidProperties() as $invalidProperty ) {
                            Log::error( "Unable to perform {$syncAction}: $invalidProperty" );
                            $syncAction->errors[] = $invalidProperty;
                        }
                    }
                    break;
                case SyncAction::ACTION_DO_NOTHING:
//                    Log::debug("Successfully did nothing");
                    break;
            }
        }

        if( !$contacts ) {
            Log::warning( "There were no suitable contacts to sync" );
            return $syncActions;
        }

        $n = count( $contacts );
        Log::info( "About to send $n contacts to Xero" );
        try {
            /** @var Contact[] $retContacts */
            $retContacts = $this->xero->updateOrCreateContacts( $this->xeroCredentials->getTenantId(), $contacts );
//            print_r($retContacts);
            foreach( $retContacts as $retContact ) {
                if( $retContact->getHasValidationErrors() ) {
                    foreach( $retContact->getValidationErrors() as $error ) {
                        Log::error( $error->getMessage() );
                    }
                    foreach( $syncActions as $syncAction ) {
                        if( $this->getMemberId( $syncAction->customer ) == $retContact->getContactNumber() ) {
                            $syncAction->failed = true;
                            foreach( $retContact->getValidationErrors() as $error ) {
                                $syncAction->errors[] = $error->getMessage();
                            }
                            break;
                        }
                    }
                }
            }

            $this->ensureContactGroup( config( 'ixpxero.contact_group' ) );
        } catch( \XeroAPI\XeroPHP\ApiException $e ) {
            Log::error( $e->getMessage() );
            Log::error( $e->getResponseBody() );

            /** @var \XeroAPI\XeroPHP\Models\Accounting\Error $obj */
            $obj = $e->getResponseObject();
            if( $obj instanceof Error ) {
                foreach( $obj->getElements() as $item ) {
                    foreach( $item->getValidationErrors() as $err ) {
                        Log::error( $err->getMessage() );
                    }
                }
            }

            throw new \Exception( "Failed to Sync. {$e->getMessage()}", 1, $e );
        }
        return $syncActions;
    }

    private function makeXeroContact( SyncAction $syncAction ): Contact
    {
        $nullOr = function( $value ) {
            return $value ? $value : null;
        };
        $trimNull = function( array $arr ) {
            foreach( $arr as $k => $v ) {
                if( is_null( $v ) ) {
                    unset( $arr[ $k ] );
                }
            }
            return $arr;
        };

        $c = $syncAction->customer;
        $memberAsn = 'AS' . $c->getAutsys();
        if( isset( $this->seenAsns[ $memberAsn ] ) ) {
            $memberAsn .= ",id={$c->getId()}";
        }
        $this->seenAsns[ $memberAsn ] = true;

        $address_registration = new Address( $trimNull( [
            'address_type'  => Address::ADDRESS_TYPE_POBOX,
            'address_line1' => $nullOr( $c->getRegistrationDetails()->getAddress1() ),
            'address_line2' => $nullOr( $c->getRegistrationDetails()->getAddress2() ),
            'address_line3' => $nullOr( $c->getRegistrationDetails()->getAddress3() ),
            'city'          => $nullOr( $c->getRegistrationDetails()->getTownCity() ),
            'postal_code'   => $nullOr( $c->getRegistrationDetails()->getPostcode() ),
            'country'       => $nullOr( $c->getRegistrationDetails()->getCountry() ),
        ] ) );

        $address_billing = new Address( $trimNull( [
            'address_type'  => Address::ADDRESS_TYPE_STREET,
            'address_line1' => $nullOr( $c->getBillingDetails()->getBillingAddress1() ),
            'address_line2' => $nullOr( $c->getBillingDetails()->getBillingAddress2() ),
            'address_line3' => $nullOr( $c->getBillingDetails()->getBillingAddress3() ),
            'city'          => $nullOr( $c->getBillingDetails()->getBillingTownCity() ),
            'postal_code'   => $nullOr( $c->getBillingDetails()->getBillingPostcode() ),
            'country'       => $nullOr( $c->getBillingDetails()->getBillingCountryName() ),
            'attention_to'  => $nullOr( $c->getBillingDetails()->getBillingContactName() ),
        ] ) );
        $addresses = [];
        if( $address_registration->valid() && "{}" != "{$address_registration}" ) {
            $addresses[] = $address_registration;
        }
        if( $address_billing->valid() && "{}" != "{$address_billing}" ) {
            $addresses[] = $address_billing;
        }

        $phones = [];
        // Xero wants all of these fields, we cannot provide them (at least easily) from a single field.
        // TODO: look into https://github.com/Propaganistas/Laravel-Phone later
//        $phone = new Phone([
//            'phone_type' => Phone::PHONE_TYPE_OFFICE,
//            'phone_number' => $nullOr($c->getBillingDetails()->getBillingTelephone()),
//            'phone_area_code' => $nullOr($c->getBillingDetails()->getBillingCountry()),
//            'phone_country_code' => $nullOr($c->getBillingDetails()->getBillingCountry()),
//        ]);
//        if ($phone->valid() && '{}' != "{$phone}") {
//            $phones[] = $phone;
//        }
        $persons = [];
        $roleStr = config( 'ixpxero.billing_contact_role' );
        /** @var \IXP\Models\ContactGroup $role */
        $role = IxpContactGroup::where('name', $roleStr);
        /**
         * Xero will throw an if we try to add contacts when the primary contact does not have an email address
         */
        $hasPrimaryEmail = (bool)$c->getBillingDetails()->getBillingEmail();

        if( $role && $hasPrimaryEmail ) {
            foreach( $c->getContacts() as $customerContact ) {
                Log::debug( "Finding contacts to add for role {$roleStr} (id={$role->getId()})" );
                $hasGroup = $customerContact->getGroups()->exists( function( $key, $element ) use ( $role ) {
                    return $role === $element;
                } );
                if( $hasGroup ) {
                    $persons[] = new ContactPerson( [
                        'first_name'        => $customerContact->getName(),
                        'email_address'     => $customerContact->getEmail(),
                        'include_in_emails' => true,
                    ] );
                }
            }
        } else {
            if( !$role ) {
                Log::error( "Unable to find Contact Group `{$roleStr}` in our local database, please make sure it exists" );
            }
        }

        $companyName = $nullOr( $c->getRegistrationDetails()->getRegisteredName() ) ?? $c->getName();

        return new Contact( [
            'contact_number'  => $this->getMemberId( $c ),
            'account_number'  => $memberAsn,
            'name'            => $companyName,
            'first_name'      => $nullOr( $c->getBillingDetails()->getBillingContactName() ),
            'addresses'       => $addresses ? $addresses : null,
            'email_address'   => $nullOr( $c->getBillingDetails()->getBillingEmail() ),
            'phones'          => $phones ? $phones : null,
            'tax_number'      => $nullOr( $c->getBillingDetails()->getVatNumber() ),
            'contact_persons' => $persons ? $persons : null,
        ] );
    }

    private function ensureContactGroup( string $configGroupName )
    {
        if( !$configGroupName ) {
            Log::debug( "No config for contact group name, not adding contacts to any group" );
            return;
        }

        $groupToAddTo = null;
        $names = [];
        // get the contact group id of the group we want
        foreach( $this->listXeroAccountingContactGroups() as $group ) {
            $names[] = $group->getName();
            if( $group->getName() == $configGroupName ) {
                $groupToAddTo = $group;
                Log::debug( "Group To Add To {$group->getContactGroupId()}" );
                break;
            }
        }
        if( !$groupToAddTo ) {
            $validNames = count( $names ) ? 'Valid Names include `' . implode( '`, `', $names ) . '`' : 'No Groups configured in Xero';
            throw new \Exception( "Specified Contact Group `{$configGroupName}` does not exist in Xero. {$validNames}" );
        }


        // now make sure these users are all in their groups
        // 1. get a fresh set of contacts (so any created ones have their id)
        $syncActions = $this->prepareSync();
        $toUpdate = new Contacts();
        foreach( $syncActions as $syncAction ) {
            $accountingContact = $syncAction->accountingContact;
            $found = false;
            foreach( $accountingContact->getContactGroups() as $group ) {
                if( $group->getName() == $configGroupName ) {
                    $found = true;
                    break;
                }
            }
            if( !$found ) {
                // contact is not yet in the right group
                $c = new Contact( [ 'contact_id' => $accountingContact->getContactId() ] );
                $toUpdate[] = $c;
            }
        }

        $n = count( $toUpdate );
        Log::debug( "$n contacts require being added to the {$configGroupName} (id={$groupToAddTo->getContactGroupId()}) group" );
        if( $toUpdate ) {
            Log::info( "Updating contact groups" );
            $result = $this->xero->createContactGroupContacts( $this->xeroCredentials->getTenantId(), $groupToAddTo->getContactGroupId(), $toUpdate );
//            print_r($result);
        }

    }
}