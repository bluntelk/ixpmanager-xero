<?php

namespace bluntelk\IxpManagerXero\Services;

use IXP\Models\Customer;

trait CustomerTrait
{
    public function getMemberId( Customer $customer ): string
    {
        return 'MemberId=' . $customer->id;
    }

    /**
     * @return Customer[]
     */
    public function listIxpCustomers(): iterable
    {
        $customers = [];
        foreach (Customer::active()->orderBy('name')->get() as $customer) {
            $key = $this->getMemberId($customer);
            $customers[$key] = $customer;
        }

        return $customers;
    }
}