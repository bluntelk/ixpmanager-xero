<?php

return [
    /**
     * Only sync contacts of these types
     */
    'sync_customer_types' => [
        \Entities\Customer::TYPE_FULL,
        \Entities\Customer::TYPE_ASSOCIATE,
        \Entities\Customer::TYPE_PROBONO,
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
];