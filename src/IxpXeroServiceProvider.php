<?php

namespace bluntelk\IxpManagerXero;

use bluntelk\IxpManagerXero\Console\Commands\SyncCommand;
use bluntelk\IxpManagerXero\Controllers\XeroController;
use bluntelk\IxpManagerXero\Services\XeroSync;
use Illuminate\Foundation\Application;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Console\Output\ConsoleOutput;
use Webfox\Xero\OauthCredentialManager;
use XeroAPI\XeroPHP\Api\AccountingApi;

class IxpXeroServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->make( XeroController::class );
        $this->mergeConfigFrom( __DIR__ . '/../config/ixpxero.php', 'ixpxero' );

        $this->app->bind( XeroSync::class, function( Application $app ) {
            return new XeroSync(
                $app->make( OauthCredentialManager::class ),
                $app->make( AccountingApi::class )
            );
        } );
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadRoutesFrom( __DIR__ . '/routes.php' );
        $this->loadViewsFrom( __DIR__ . '/views/', 'ixpxero' );
        $this->publishes( [
            __DIR__ . '/views'                     => resource_path( 'views/vendor/ixpxero' ),
            __DIR__ . '/config/ixpxero-config.php' => config_path( 'ixpxero-config.php' ),
        ] );

        if( $this->app->runningInConsole() ) {
            $this->commands( [
                SyncCommand::class,
            ] );
        }
    }
}
