<?php

namespace bluntelk\IxpManagerXero\Controllers;

use bluntelk\IxpManagerXero\Services\XeroSync;
use Illuminate\Http\Request;
use IXP\Http\Controllers\Controller;
use Webfox\Xero\OauthCredentialManager;

class XeroController extends Controller
{
    protected $requiredScopes = [ 'accounting.contacts', 'accounting.settings.read' ];

    public function __construct()
    {
        $this->middleware( 'auth' );
    }

    public function index( Request $request, OauthCredentialManager $xeroCredentials )
    {
        try {
            // Check if we've got any stored credentials
            if( $xeroCredentials->exists() ) {
                /*
                 * We have stored credentials so we can resolve the AccountingApi,
                 * If we were sure we already had some stored credentials then we could just resolve this through the controller
                 * But since we use this route for the initial authentication we cannot be sure!
                 */
                $xero = resolve( \XeroAPI\XeroPHP\Api\AccountingApi::class );
                $organisationName = $xero->getOrganisations( $xeroCredentials->getTenantId() )->getOrganisations()[ 0 ]->getName();
                $user = $xeroCredentials->getUser();
                $username = "{$user['given_name']} {$user['family_name']} ({$user['username']})";
            }


            $incorrectSetup = false;
            foreach( $this->requiredScopes as $scope ) {
                if( !in_array( $scope, config( 'xero.oauth.scopes' ) ) ) {
                    $incorrectSetup = true;
                }
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
            'incorrectSetup'   => $incorrectSetup ?? false,
        ] );
    }

    public function performSync(Request $request,  XeroSync $xeroSync )
    {
        $actions = [];
        $performing = false;
        try {
            if ('yes' == $request->get('perform')) {
                $performing = true;
                $actions = $xeroSync->performSync();
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
}

