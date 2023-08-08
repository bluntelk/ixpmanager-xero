<?php

namespace bluntelk\IxpManagerXero\Services;

use Webfox\Xero\OauthCredentialManager;
use XeroAPI\XeroPHP\Api\AccountingApi;

class XeroInvoices
{
    private OauthCredentialManager $xeroCredentials;
    private AccountingApi $accountingApi;

    public function __construct( OauthCredentialManager $xeroCredentials, AccountingApi $accountingApi )
    {

        $this->xeroCredentials = $xeroCredentials;
        $this->accountingApi = $accountingApi;
    }

    public function fetchRepeatingInvoices(): array
    {
        $repeatingInvoices = $this->accountingApi->getRepeatingInvoices(
            $this->xeroCredentials->getTenantId(),
        );

        return $repeatingInvoices->getRepeatingInvoices() ?? [];
    }

}