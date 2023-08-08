<?php

namespace bluntelk\IxpManagerXero\Controllers;

use bluntelk\IxpManagerXero\Services\XeroInvoices;
use bluntelk\IxpManagerXero\Services\XeroSync;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use IXP\Http\Controllers\Controller;
use Webfox\Xero\OauthCredentialManager;
use XeroAPI\XeroPHP\Api\AccountingApi;

class XeroController extends Controller
{

    public function __construct()
    {
        $this->middleware( 'auth' );
    }

    public function index( Request $request, OauthCredentialManager $xeroCredentials, XeroSync $xeroSync ): Factory|View|Application
    {
        try {
            // Check if we've got any stored credentials
            if( $xeroCredentials->exists() ) {
                /*
                 * We have stored credentials so we can resolve the AccountingApi,
                 * If we were sure we already had some stored credentials then we could just resolve this through the controller
                 * But since we use this route for the initial authentication we cannot be sure!
                 */
                $xero = resolve( AccountingApi::class );
                $organisationName = $xero->getOrganisations( $xeroCredentials->getTenantId() )->getOrganisations()[ 0 ]->getName();
                $user = $xeroCredentials->getUser();
                $username = "{$user['given_name']} {$user['family_name']} ({$user['username']})";
            }

        } catch( \throwable $e ) {
            // This can happen if the credentials have been revoked or there is an error with the organisation (e.g. it's expired)
            $error = $e->getMessage();
            $errorExtra = $e->getTraceAsString();
        }

        return view( 'ixpxero::index', [
            'connected'        => $xeroCredentials->exists(),
            'error'            => $error ?? false,
            'errorExtra'       => $errorExtra ?? '',
            'organisationName' => $organisationName ?? false,
            'username'         => $username ?? false,
            'incorrectSetup'   => !$xeroSync->isXeroConfigValid() ?? false,
        ] );
    }

    public function performSync( Request $request, XeroSync $xeroSync ): Factory|View|Application
    {
        $actions = [];
        $performing = false;
        try {
            if( 'yes' == $request->get( 'perform' ) ) {
                $performing = true;
                $actions = $xeroSync->performSyncAll();
            } else {
                $actions = $xeroSync->prepareSync();
            }
        } catch( \throwable $e ) {
            // This can happen if the credentials have been revoked or there is an error with the organisation (e.g. it's expired)
            $error = $e->getMessage();
            $errorExtra = $e->getTraceAsString();
        }

        return view( 'ixpxero::sync_actions', [
            'error'      => $error ?? false,
            'errorExtra' => $errorExtra ?? '',
            'actions'    => $actions,
            'performing' => $performing,
        ] );
    }


    public function showRepeatingInvoices( Request $request, XeroInvoices $invoices ): Factory|View|Application
    {
        return view( 'ixpxero::repeating_invoices', [
            'invoices' => $invoices->fetchRepeatingInvoices(),
        ] );
    }
}

