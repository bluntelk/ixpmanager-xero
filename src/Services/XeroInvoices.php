<?php

namespace bluntelk\IxpManagerXero\Services;

use bluntelk\IxpManagerXero\Models\XeroLineItem;
use Illuminate\Support\Facades\DB;
use IXP\Models\Customer;
use Psr\Log\LoggerInterface;
use Webfox\Xero\OauthCredentialManager;
use XeroAPI\XeroPHP\Api\AccountingApi;
use XeroAPI\XeroPHP\ApiException;
use XeroAPI\XeroPHP\Models\Accounting\Items;
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

    public function fetchXeroLineItems(): Items
    {
        try {
            return $this->accountingApi->getItems(
                $this->xeroCredentials->getTenantId(),
            );
        } catch( ApiException $e ) {
            $this->logger->error( "Failed to communicate with Xero to get invoices line items" );
            $this->logger->error( $e->getMessage() );
            return new Items();
        }
    }

    /**
     * Get the list of local service names and the matching Xero line item to check against
     *
     * @return array
     */
    public function getServices(): array
    {
        $services = [];

        foreach( $this->getServiceList() as $local ) {
            $localServiceName = $this->getGeneralLineItemCode( $local->vlan_name, $local->speed );

            $itemMatch = XeroLineItem::firstOrNew( [ 'local_service' => $localServiceName, 'cust_id' => null ] );
            $itemMatch->save();
        }


        return XeroLineItem::orderBy( 'local_service' )
            ->select('cust.name as customer_name', 'xero_line_items.*')
            ->leftJoin('cust', 'cust_id', '=', 'cust.id')
            ->get()
            ->toArray();
    }

    private function getServiceList(): array
    {
        return DB::table( 'vlan' )
            ->select( 'vlan.name as vlan_name', 'physicalinterface.speed' )
            ->join( 'vlaninterface', 'vlan.id', '=', 'vlaninterface.vlanid' )
            ->join( 'virtualinterface', 'vlaninterface.virtualinterfaceid', '=', 'virtualinterface.id' )
            ->join( 'physicalinterface', 'physicalinterface.virtualinterfaceid', '=', 'virtualinterface.id' )
            ->whereNotNull( 'physicalinterface.speed' )
            ->groupBy( 'vlan.name', 'physicalinterface.speed' )
            ->orderBy( 'vlan.name' )
            ->orderBy( 'physicalinterface.speed' )
            ->get()
            ->toArray();
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
     * @param string $vlanName
     * @param int $speed
     * @return string
     */
    protected function getGeneralLineItemCode( string $vlanName, int $speed ): string
    {
        $speed = $speed / 1000;
        return str_replace( ' ', '-', strtolower( "{$vlanName}-{$speed}gbps" ) );
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
            if( $customer->type != Customer::TYPE_FULL ) {
                continue;
            }
            $invoices = [];
            foreach( $repeatingInvoices as $repeatingInvoice ) {
                // we stash "MemberId=<id>" in the ContactNumber
                $key = $repeatingInvoice->getContact()?->getContactNumber() ?? '';
                if( $key == $id ) {
                    $invoices[] = $repeatingInvoice;
                }
            }

            $servicesNeedingBilling = [];
            $services = $this->fetchCustomerServices( $customer );
            if( $invoices ) {
                foreach( $services as $service ) {
                    $generalLineItemCode = $this->getGeneralLineItemCode( $service->vlan_name, $service->speed );
                    $customerLineItemCode = $this->makeCustomerLineItemCode( $service );

                    foreach( $invoices as $invoice ) {
                        foreach( $invoice->getLineItems() as $lineItem ) {
                            if( $lineItem->getItemCode() == $generalLineItemCode ) {
                                $this->logger->debug( "Customer has general line item configured repeating line item for this service", [ $service ] );
                                continue 3;
                            }
                            if( $lineItem->getItemCode() == $customerLineItemCode ) {
                                $this->logger->debug( "Customer has customer specific configured repeating line item for this service", [ $service ] );
                                continue 3;
                            }
                        }
                    }
                    $servicesNeedingBilling[] = $service;
                }
            } else {
                $servicesNeedingBilling = $services;
            }

            $ret[] = [
                'customer'               => $customer,
                'invoices'               => $invoices,
                'services'               => $services,
                'servicesNeedingBilling' => $servicesNeedingBilling,
            ];
        }

        return $ret;
    }

}