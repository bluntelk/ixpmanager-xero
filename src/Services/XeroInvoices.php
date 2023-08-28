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
            ->select( 'cust.name as customer_name', 'xero_line_items.*' )
            ->leftJoin( 'cust', 'cust_id', '=', 'cust.id' )
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
                'cust.id as cust_id',
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

    protected function getXeroItemCode( string $localService, int $custId ): ?string
    {
        $items = XeroLineItem::where( 'local_service', '=', $localService )
            ->get();

        foreach( $items as $item ) {
            if( $item[ 'cust_id' ] === $custId ) {
                return $item[ 'xero_service' ];
            }
        }

        if( $items->count() ) {
            return $items[ 0 ][ 'xero_service' ];
        }

        return null;
    }

    public function buildReportingData(): array
    {
        $repeatingInvoices = $this->fetchRepeatingInvoices();

        $ret = [];
        foreach( $this->listIxpCustomers() as $id => $customer ) {
            if( $data = $this->buildReportingDataForCustomer( $id, $customer, $repeatingInvoices ) ) {
                $ret[] = $data;
            }
        }

        return $ret;
    }

    public function buildReportingDataForCustomer( string $id, Customer $customer, ?array $repeatingInvoices ): array
    {
        $repeatingInvoices ??= $this->fetchRepeatingInvoices();

        if( $customer->type != Customer::TYPE_FULL || !$customer->statusNormal() ) {
            return [];
        }
        $invoices = [];
        $this->logger->debug( "Repeating Invoice Count", [ count( $repeatingInvoices ) ] );
        $this->logger->debug( "Working With Customer", [ 'key' => $id, 'customer' => $customer->name ] );

        foreach( $repeatingInvoices as $repeatingInvoice ) {
            // we stash "MemberId=<id>" in the ContactNumber
            $key = $repeatingInvoice->getContact()?->getContactNumber() ?? '';
            if( $key == $id ) {
                $this->logger->debug( "Matching Invoice For Customer", [ 'id' => $repeatingInvoice->getId() ] );
                $invoices[] = $repeatingInvoice;
            }
        }
        $this->logger->debug( "Customer Repeating Invoices", [ 'count' => count( $invoices ) ] );

        $servicesNeedingBilling = [];
        $services = $this->fetchCustomerServices( $customer );
        $this->logger->debug( "Customer Services Count", [ 'count' => count( $services ) ] );
        if( $invoices ) {
            foreach( $services as $service ) {
                $localServiceCode = $this->getGeneralLineItemCode( $service->vlan_name, $service->speed );
                $xeroLineItemCode = $this->getXeroItemCode( $localServiceCode, $service->cust_id );

                $this->logger->debug(
                    "Attempting to match Customer Service",
                    [ $service, $localServiceCode, $xeroLineItemCode ]
                );
                foreach( $invoices as $invoice ) {
                    if( $invoice::STATUS_AUTHORISED !== $invoice->getStatus() ) {
                        $this->logger->info( "Ignoring Repeating invoice Status", [ 'invoice' => $invoice->getId(), 'status' => $invoice->getStatus() ] );
                        continue;
                    }
                    foreach( $invoice->getLineItems() as $lineItem ) {
                        if( $xeroLineItemCode && $lineItem->getItemCode() == $xeroLineItemCode ) {
                            $this->logger->debug(
                                "We have a repeating invoice for this customer with a matching line item",
                                [
                                    'local'   => $localServiceCode,
                                    'xero'    => $lineItem->getItemCode(),
                                    'invoice' => $invoice->getId(),
                                    'status'  => $invoice->getStatus(),
                                ]
                            );
                            continue 3;
                        }
                    }
                }

                $servicesNeedingBilling[] = $service;
            }
        } else {
            $servicesNeedingBilling = $services;
        }

        return [
            'customer'               => $customer,
            'invoices'               => $invoices,
            'services'               => $services,
            'servicesNeedingBilling' => $servicesNeedingBilling,
        ];
    }
}