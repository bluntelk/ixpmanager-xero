<?php

namespace bluntelk\IxpManagerXero\Services;

use Entities\Customer;

class XeroSync
{
    private $config;

    public function __construct( $config) {

        $this->config = $config;
    }

    protected function connect()
    {

    }

    public function syncCustomerToXero(Customer $customer)
    {

    }
}