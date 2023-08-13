<?php

namespace bluntelk\IxpManagerXero;

use bluntelk\IxpManagerXero\Console\Commands\SyncCommand;
use bluntelk\IxpManagerXero\Controllers\XeroController;
use bluntelk\IxpManagerXero\Services\XeroInvoices;
use bluntelk\IxpManagerXero\Services\XeroSync;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use IXP\Events\Customer\BillingDetailsChanged;
use Psr\Log\LoggerInterface;
use Webfox\Xero\OauthCredentialManager;
use XeroAPI\XeroPHP\Api\AccountingApi;

class IxpXeroServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom( __DIR__ . '/../config/ixpxero.php', 'ixpxero' );

        $this->app->bind( XeroSync::class, function( Application $app ) {
            return new XeroSync(
                $app->make( OauthCredentialManager::class ),
                $app->make( AccountingApi::class )
            );
        } );
        $this->app->bind( XeroInvoices::class, function( Application $app ) {
            return new XeroInvoices(
                $app->make( LoggerInterface::class ),
                $app->make( OauthCredentialManager::class ),
                $app->make( AccountingApi::class )
            );
        } );

        $this->app->make( XeroController::class );
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->loadRoutesFrom( __DIR__ . '/../routes/web.php' );
        $this->loadMigrationsFrom( __DIR__ . '/../database/migrations' );
        $this->loadViewsFrom( __DIR__ . '/../resources/views', 'ixpxero' );
        $this->publishes( [
            __DIR__ . '/../resources/views'    => resource_path( 'views/vendor/ixpxero' ),
            __DIR__ . '/../config/ixpxero.php' => config_path( 'ixpxero-config.php' ),
        ] );

        if( $this->app->runningInConsole() ) {
            $this->commands( [
                SyncCommand::class,
            ] );
        } else {
            Event::listen( BillingDetailsChanged::class, function( BillingDetailsChanged $event ) {
                $customer = $event->cbd->customer;
                try {
                    app( XeroSync::class )->performSyncOne( $customer );
                } catch( \Exception $e ) {
                    Log::error( "Failed to perform sync", [ $e->getMessage() ] );
                }
            } );
        }
    }
}
