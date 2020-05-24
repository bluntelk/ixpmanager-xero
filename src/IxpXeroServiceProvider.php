<?php

namespace bluntelk\IxpManagerXero;


use bluntelk\IxpManagerXero\Console\Commands\SyncCommand;
use bluntelk\IxpManagerXero\Controllers\XeroController;
use bluntelk\IxpManagerXero\Services\XeroSync;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class IxpXeroServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->make(XeroController::class);
        $this->mergeConfigFrom(__DIR__ . '/../config/xero.php', 'xero');
        config('xero.oauth.scopes', ['openid', 'email', 'profile', 'offline_access', 'accounting.contacts']);
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadViewsFrom(__DIR__ . '/views/', 'ixpxero');
        $this->publishes([
            __DIR__.'/views' => resource_path('views/vendor/ixpxero'),
            __DIR__.'/config/ixpxero-config.php' => config_path('ixpxero-config.php')
        ]);

        $this->app->singleton(XeroSync::class, function(Application $app) {
            return new XeroSync(config('ixpxero.config'));
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncCommand::class
            ]);
        }
    }
}
