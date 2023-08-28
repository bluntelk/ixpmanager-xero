<?php

namespace bluntelk\IxpManagerXero\Services;

use bluntelk\IxpManagerXero\Sync\SyncAction;
use Illuminate\Support\Facades\Log;
use IXP\Exceptions\GeneralException;
use IXP\Models\ContactGroup as IxpContactGroup;
use Ixp\Models\Customer;
use Webfox\Xero\OauthCredentialManager;
use XeroAPI\XeroPHP\Api\AccountingApi;
use XeroAPI\XeroPHP\Models\Accounting\Address;
use XeroAPI\XeroPHP\Models\Accounting\Contact;
use XeroAPI\XeroPHP\Models\Accounting\ContactGroup;
use XeroAPI\XeroPHP\Models\Accounting\ContactGroups;
use XeroAPI\XeroPHP\Models\Accounting\ContactPerson;
use XeroAPI\XeroPHP\Models\Accounting\Contacts;
use XeroAPI\XeroPHP\Models\Accounting\Error;

class XeroSync
{
    use CustomerTrait;

    private AccountingApi $xero;
    private OauthCredentialManager $xeroCredentials;

    private array $seenAsns = [];

    protected array $requiredScopes = [
        'accounting.contacts',
        'accounting.settings.read',
        'accounting.transactions.read'
    ];

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
            if( !in_array( $customer->type, config( 'ixpxero.sync_customer_types' ) ) ) {
                try {
                    Log::debug( "Ignoring Customer ({$customer->name} - Type: {$customer->givenType($customer->type)})" );
                } catch( GeneralException $e ) {
                    Log::debug( "Ignoring Customer ({$customer->name} - Type: Unknown)" );
                }
                continue;
            }
            // match based on ASN
            $memberAsn = 'AS' . $customer->autsys;
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
    public function performSync( array $syncActions ): array
    {
        if( !$syncActions ) {
            Log::warning( "No Sync Actions, we do not have anyone added." );
            return [];
        }
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
            return $value ?: null;
        };
        $trimNull = function( array $arr ) {
            foreach( $arr as $k => $v ) {
                if( is_null( $v ) ) {
                    unset( $arr[ $k ] );
                }
            }
            return $arr;
        };

        /** @var Customer $c */
        $c = $syncAction->customer;
        $memberAsn = 'AS' . $c->autsys;
        if( isset( $this->seenAsns[ $memberAsn ] ) ) {
            $memberAsn .= ",id={$c->id}";
        }
        $this->seenAsns[ $memberAsn ] = true;

        $address_registration = new Address( $trimNull( [
            'address_type'  => Address::ADDRESS_TYPE_POBOX,
            'address_line1' => $nullOr( $c->companyRegisteredDetail->address1 ),
            'address_line2' => $nullOr( $c->companyRegisteredDetail->address2 ),
            'address_line3' => $nullOr( $c->companyRegisteredDetail->address3 ),
            'city'          => $nullOr( $c->companyRegisteredDetail->townCity ),
            'postal_code'   => $nullOr( $c->companyRegisteredDetail->postcode ),
            'country'       => $nullOr( $c->companyRegisteredDetail->country ),
        ] ) );

        $address_billing = new Address( $trimNull( [
            'address_type'  => Address::ADDRESS_TYPE_STREET,
            'address_line1' => $nullOr( $c->companyBillingDetail->billingAddress1 ),
            'address_line2' => $nullOr( $c->companyBillingDetail->billingAddress2 ),
            'address_line3' => $nullOr( $c->companyBillingDetail->billingAddress3 ),
            'city'          => $nullOr( $c->companyBillingDetail->billingTownCity ),
            'postal_code'   => $nullOr( $c->companyBillingDetail->billingPostcode ),
            'country'       => $nullOr( $c->companyBillingDetail->billingCountry ),
            'attention_to'  => $nullOr( $c->companyBillingDetail->billingContactName ),
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
        $role = IxpContactGroup::where( 'name', $roleStr )->first();
        /**
         * Xero will throw an if we try to add contacts when the primary contact does not have an email address
         */
        $hasPrimaryEmail = (bool)$c->companyBillingDetail->billingEmail;

        if( $role && $hasPrimaryEmail ) {
            foreach( $c->contacts as $customerContact ) {
                Log::debug( "Finding contacts to add for role {$roleStr} (id={$role->id})" );
                $hasGroup = $customerContact->contactGroups()->exists( function( $key, $element ) use ( $role ) {
                    return $role === $element;
                } );
                if( $hasGroup ) {
                    $persons[] = new ContactPerson( [
                        'first_name'        => $customerContact->name,
                        'email_address'     => $customerContact->email,
                        'include_in_emails' => true,
                    ] );
                }
            }
        } else {
            if( !$role ) {
                Log::error( "Unable to find Contact Group `{$roleStr}` in our local database, please make sure it exists" );
            }
        }

        $companyName = $nullOr( $c->companyRegisteredDetail->registeredName ) ?? $c->name;

        return new Contact( [
            'contact_number'  => $this->getMemberId( $c ),
            'account_number'  => $memberAsn,
            'name'            => $companyName,
            'first_name'      => $nullOr( $c->companyBillingDetail->billingContactName ),
            'addresses'       => $addresses ?: null,
            'email_address'   => $nullOr( $c->companyBillingDetail->billingEmail ),
            'phones'          => $phones ?: null,
            'tax_number'      => $nullOr( $c->companyBillingDetail->vatNumber ),
            'contact_persons' => $persons ?: null,
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