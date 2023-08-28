<?php

namespace bluntelk\IxpManagerXero\Sync;

use Ixp\Models\Customer;
use Illuminate\Support\Facades\Event;
use IXP\Events\Customer\BillingDetailsChanged;
use XeroAPI\XeroPHP\Models\Accounting\Contact;

class SyncAction
{
    const ACTION_CREATE = 'create-in-xero';
    const ACTION_UPDATE = 'update-existing-xero';
    const ACTION_DO_NOTHING = 'do-nothing';

    /**
     * one of the ACTION_* constants above
     * @var string
     */
    public $action;

    /** @var \Ixp\Models\Customer */
    public Customer $customer;


    public $accountingContact;
    /**
     * @var bool
     */
    public bool $performed = false;

    public $errors = [];
    public $failed = false;

    /**
     * @param string $action
     * @param Customer $customer
     * @param Contact|null $accountingContact
     * @param bool $performed
     */
    public function __construct( string $action, Customer $customer, ?Contact $accountingContact, bool $performed = false )
    {
        $this->customer = $customer;
        $this->accountingContact = $accountingContact;
        $this->action = $action;
        $this->performed = $performed;
    }

    public function __toString()
    {
        switch( $this->action ) {
            case SyncAction::ACTION_CREATE:
                return "create IXP Member `{$this->customer->name}` in Xero";
            case SyncAction::ACTION_UPDATE:
                return "update IXP Member `{$this->customer->name}` details against Xero customer `{$this->customer->name}`";
            case SyncAction::ACTION_DO_NOTHING:
                return "absolutely nothing with member `{$this->customer->name}`";
            default:
                return "an unknown action with member `{$this->customer->name}`";
        }
    }
}