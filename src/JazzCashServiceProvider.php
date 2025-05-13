<?php

namespace Aticmatic\JazzCash;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Support\DeferrableProvider;
use Aticmatic\JazzCash\Http\Controllers\JazzCashCallbackController; // Ensure this path is correct

class JazzCashServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Merge package configuration with the application's published version
        $this->mergeConfigFrom(
            __DIR__.'/../config/jazzcash.php',
            'jazzcash'
        );

        // Bind the JazzCashService to the service container
        // Referenced by [26, 27] for singleton binding
        $this->app->singleton('jazzcash.service', function ($app) {
            return new JazzCashService($app['config']);
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Publish the configuration file
        // Referenced by [5, 25] for publishing assets
        if ($this->app->runningInConsole()) {
            $this->publishes(, 'jazzcash-config');
        }

        // Load package routes
        // Referenced by [5, 31] for loading routes
        $this->loadRoutesFrom(__DIR__.'/../routes/jazzcash.php');

        // Optionally, publish views or migrations if your package had them
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'jazzcash');
        // $this->publishes(, 'jazzcash-views');
        // $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // $this->publishes(, 'jazzcash-migrations');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return;
    }
}