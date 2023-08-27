<?php

namespace bluntelk\IxpManagerXero\Http\Controllers;

use bluntelk\IxpManagerXero\Http\Requests\XeroLineItem\StoreRequest;
use bluntelk\IxpManagerXero\Models\XeroLineItem;
use bluntelk\IxpManagerXero\Services\XeroInvoices;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use IXP\Http\Controllers\Controller;
use IXP\Utils\View\Alert\{
    Alert,
    Container as AlertContainer
};

class XeroLineItemController extends Controller
{

    public function __construct()
    {
        $this->middleware( 'auth' );
    }

    public function index( Request $request, XeroInvoices $invoices ): Factory|View|Application
    {
        return view( 'ixpxero::line-items/index', [
            'services' => $invoices->getServices(),
        ] );
    }

    protected function getXeroLineItemOptions(XeroInvoices $invoices): array
    {
        $items = [];
        foreach ($invoices->fetchXeroLineItems()->getItems() as $item) {
            $items[$item->getCode()] = $item->getName();
        }

        return $items;
    }

    public function create( XeroInvoices $invoices )
    {
        return view(
            'ixpxero::line-items/edit',
            [
                'item'           => false,
                'customers'      => [['id' => '', 'name' => 'All Customers']] + $invoices->listIxpCustomers(),
                'local_services' => $invoices->getServices(),
                'xero_services' =>  [''  => 'No Mapping'] + $this->getXeroLineItemOptions($invoices),
            ]
        );
    }

    public function store( StoreRequest $request )
    {
        // make sure we do not have a duplicate

        XeroLineItem::create( $request->all() );
        AlertContainer::push( "Xero Line Item Mapping Created.", Alert::SUCCESS );

        return redirect()->route( 'ixpxero.line-item.index' );
    }

    public function edit( $id, XeroInvoices $invoices )
    {
        $lineItem = XeroLineItem::find( $id );

        return view(
            'ixpxero::line-items/edit',
            [
                'item'           => $lineItem,
                'customers'      => [['id' => '', 'name' => 'All Customers']] + $invoices->listIxpCustomers(),
                'local_services' => $invoices->getServices(),
                'xero_services' =>  [''  => 'No Mapping'] + $this->getXeroLineItemOptions($invoices),
            ]
        );
    }

    public function update( StoreRequest $request, $id )
    {
        XeroLineItem::find( $id )->update( [
            'local_service' => $request->local_service,
            'xero_service'  => $request->xero_service,
            'cust_id'       => $request->cust_id,
        ] );
        AlertContainer::push( "Xero Line Item Mapping Updated.", Alert::SUCCESS );

        return redirect()->route( 'ixpxero.line-item.index' );
    }

    public function show( $id )
    {
        $lineItem = XeroLineItem::find( $id );

        return view( 'ixpxero::line-items/show', [ 'item' => $lineItem ] );
    }

    public function destroy( $id )
    {
        XeroLineItem::find( $id )->delete();
        AlertContainer::push( "Xero Line Item Mapping Deleted.", Alert::SUCCESS );

        return redirect()->route( 'ixpxero.line-item.index' );
    }
}
