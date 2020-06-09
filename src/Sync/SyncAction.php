<?php

namespace bluntelk\IxpManagerXero\Sync;

use Entities\Customer;
use Illuminate\Support\Facades\Event;
use IXP\Events\Customer\BillingDetailsChanged;

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

    /** @var \Entities\Customer */
    public $customer;


    public $accountingContact;
    /**
     * @var bool
     */
    public $performed;

    public $errors = [];
    public $failed = false;

    public function __construct(string $action, Customer $customer, $accountingContact, bool $performed = false)
    {
        $this->customer = $customer;
        $this->accountingContact = $accountingContact;
        $this->action = $action;
        $this->performed = $performed;
    }

    public function __toString()
    {
        switch($this->action) {
            case SyncAction::ACTION_CREATE:
                return "create IXP Member `{$this->customer->getName()}` in Xero";
            case SyncAction::ACTION_UPDATE:
                return "update IXP Member `{$this->customer->getName()}` details against Xero customer `{$this->customer->getName()}`";
            case SyncAction::ACTION_DO_NOTHING:
                return "absolutely nothing with member `{$this->customer->getName()}`";
            default:
                return "an unknown action with member `{$this->customer->getName()}`";
        }
    }
}