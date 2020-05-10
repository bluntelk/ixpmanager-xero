<?php

namespace bluntelk\IxpManagerXero;

use Illuminate\Support\ServiceProvider;

class IxpManagerXeroServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->make(IxpManagerXeroController::class);
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadViewsFrom(__DIR__ . '/views/', 'IxpManagerXero');
        $this->publishes([
            __DIR__.'/views' => base_path('resources/views/bluntelk/IxpManagerXero')
        ]);
    }
}
