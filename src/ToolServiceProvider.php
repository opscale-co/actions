<?php

namespace Opscale\Actions;

use Illuminate\Support\ServiceProvider;
use Lorisleiva\Actions\ActionManager;
use Opscale\Actions\DesignPatterns\MCPToolDesignPattern;
use Opscale\Actions\DesignPatterns\NovaActionDesignPattern;

class ToolServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Extend the ActionManager to include our custom design patterns
        $this->app->afterResolving(ActionManager::class, function (ActionManager $manager) {
            // Register Nova action design pattern
            $manager->registerDesignPattern(new NovaActionDesignPattern);

            // Register MCP tool design pattern
            $manager->registerDesignPattern(new MCPToolDesignPattern);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
