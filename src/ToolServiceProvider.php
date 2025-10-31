<?php

namespace :namespace_vendor\:namespace_tool_name;

use Laravel\Nova\Events\ServingNova;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use :namespace_vendor\:namespace_tool_name\Http\Middleware\Authorize;
use Laravel\Nova\Nova;

class ToolServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadRoutes();
        /*$this->loadConfigs();

        if ($this->app->runningInConsole()) {
            $this->loadCommands();
            $this->loadMigrations();
        }
            
        Nova::serving(function (ServingNova $event) {
            $this->loadResources();
        });*/
    }

    public function register()
    {
        //
    }

    /*protected function loadResources()
    {
        Nova::resources([]);
    }

    protected function loadRoutes()
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::middleware(['nova', Authorize::class])
                ->prefix('nova-vendor/:vendor/:package_name')
                ->group(__DIR__.'/../routes/api.php');
    }
                
    protected function loadConfigs()
    {
        $filename = ':package_name.php';
        $this->publishes([
            __DIR__."/../config/$filename" => config_path($filename),
        ]);
    }

    protected function loadCommands()
    {
        $this->commands([]);
    }

    protected function loadMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ]);
    }*/
}
