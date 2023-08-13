<?php

namespace bluntelk\IxpManagerXero\Services;

use Illuminate\Support\Facades\DB;
use IXP\Models\Customer;
use Psr\Log\LoggerInterface;
use Webfox\Xero\OauthCredentialManager;
use XeroAPI\XeroPHP\Api\AccountingApi;
use XeroAPI\XeroPHP\ApiException;
use XeroAPI\XeroPHP\Models\Accounting\RepeatingInvoice;

class XeroInvoices
{
    use CustomerTrait;

    private OauthCredentialManager $xeroCredentials;
    private AccountingApi $accountingApi;
    private LoggerInterface $logger;

    public function __construct( LoggerInterface $logger, OauthCredentialManager $xeroCredentials, AccountingApi $accountingApi )
    {

        $this->xeroCredentials = $xeroCredentials;
        $this->accountingApi = $accountingApi;
        $this->logger = $logger;
    }

    /**
     * @return RepeatingInvoice[]
     */
    public function fetchRepeatingInvoices(): array
    {
        try {
            $repeatingInvoices = $this->accountingApi->getRepeatingInvoices(
                $this->xeroCredentials->getTenantId(),
            );
            return $repeatingInvoices->getRepeatingInvoices() ?? [];
        } catch( ApiException $e ) {
            $this->logger->error( "Failed to communicate with Xero to get repeating invoices" );
            $this->logger->error( $e->getMessage() );
            return [];
        }
    }

    /**
     * @param Customer $customer
     * @return array<int, object{cust_name: string, date_join: string, vlan_name: string, speed: int, location_name: string, city: string}>
     */
    public function fetchCustomerServices( Customer $customer ): array
    {
        return DB::table( 'cust' )
            ->select(
                'cust.name as cust_name',
                'cust.datejoin as date_join',
                'vlan.name as vlan_name',
                'physicalinterface.speed',
                'location.name as location_name',
                'location.city'
            )
            ->join( 'virtualinterface', 'cust.id', '=', 'virtualinterface.custid' )
            ->join( 'physicalinterface', 'physicalinterface.virtualinterfaceid', '=', 'virtualinterface.id' )
            ->join( 'vlaninterface', 'virtualinterface.id', '=', 'vlaninterface.virtualinterfaceid' )
            ->join( 'vlan', 'vlaninterface.vlanid', '=', 'vlan.id' )
            ->join( 'switchport', 'physicalinterface.switchportid', '=', 'switchport.id' )
            ->join( 'switch', 'switchport.switchid', '=', 'switch.id' )
            ->join( 'cabinet', 'switch.cabinetid', '=', 'cabinet.id' )
            ->join( 'location', 'cabinet.locationid', '=', 'location.id' )
            ->where( 'cust.id', '=', $customer->id )
            ->get()
            ->toArray();
    }

    /**
     * @param object{cust_name: string, date_join: string, vlan_name: string, speed: int, location_name: string, city: string} $item
     * @return string
     */
    protected function makeGeneralLineItemCode( object $item ): string
    {
        $speed = $item->speed / 1000;
        return strtolower( "{$item->vlan_name}-{$speed}gbps" );
    }

    /**
     * @param object{cust_name: string, date_join: string, vlan_name: string, speed: int, location_name: string, city: string} $item
     * @return string
     */
    protected function makeCustomerLineItemCode( object $item ): string
    {
        $speed = $item->speed / 1000;
        return strtolower( "{$item->cust_name}-{$item->vlan_name}-{$speed}gbps" );
    }

    public function buildReportingData(): array
    {
        $repeatingInvoices = $this->fetchRepeatingInvoices();

        $ret = [];
        foreach( $this->listIxpCustomers() as $id => $customer ) {
            $invoice = null;
            foreach( $repeatingInvoices as $repeatingInvoice ) {
                $key = $repeatingInvoice->getContact()?->getContactNumber() ?? '';
                if( $key == $id ) {
                    $invoice = $repeatingInvoice;
                }
            }

            $servicesNeedingBilling = [];
            $services = $this->fetchCustomerServices( $customer );
            if( $invoice ) {
                foreach( $services as $service ) {
                    $generalLineItemCode = $this->makeGeneralLineItemCode( $service );
                    $customerLineItemCode = $this->makeCustomerLineItemCode( $service );

                    foreach ($invoice->getLineItems() as $lineItem) {
                        if ($lineItem->getItemCode() == $generalLineItemCode) {
                            $this->logger->debug("Customer has general configured repeating line item for this service", [$service]);
                            continue 2;
                        }
                        if ($lineItem->getItemCode() == $customerLineItemCode) {
                            $this->logger->debug("Customer has customer specific configured repeating line item for this service", [$service]);
                            continue 2;
                        }
                    }
                    $servicesNeedingBilling[] = $service;
                }
            } else {
                $servicesNeedingBilling = $services;
            }

            $ret[] = [
                'customer'               => $customer,
                'invoice'                => $invoice,
                'services'               => $services,
                'servicesNeedingBilling' => $servicesNeedingBilling,
            ];
        }

        return $ret;
    }

}