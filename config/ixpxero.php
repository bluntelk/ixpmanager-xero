<?php

return [
    /**
     * Only sync contacts of these types
     */
    'sync_customer_types' => [
        \IXP\Models\Customer::TYPE_FULL,
        \IXP\Models\Customer::TYPE_ASSOCIATE,
        \IXP\Models\Customer::TYPE_PROBONO,
    ],
    /**
     * Add all of our IXP Customers to this Xero Contact Group
     *
     * Note: This value is case sensitive
     *
     * Throws an error if the specified contact group does not already exist in Xero
     *
     * @example 'IXP Members'
     */
    'contact_group' => '',

    /**
     * All contacts with this role will be added to their respective Contact in Xero
     */
    'billing_contact_role' => 'Billing',
];