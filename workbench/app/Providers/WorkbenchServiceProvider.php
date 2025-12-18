<?php

namespace Workbench\App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Facades\Mcp;
use Workbench\App\MCP\Servers\PlatformServer;
use Workbench\App\Services\Actions\ResetPassword;

class WorkbenchServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerCommand();
        $this->registerController();
        $this->registerMCP();
    }

    /**
     * Register commands for actions.
     */
    protected function registerCommand(): void
    {
        $this->commands([
            ResetPassword::class,
        ]);
    }

    /**
     * Register controller routes for actions.
     */
    protected function registerController(): void
    {
        $this->app['router']->post('/api/reset-password', ResetPassword::class);
    }

    /**
     * Register MCP server.
     */
    protected function registerMCP(): void
    {
        Mcp::local('actions-mcp', PlatformServer::class);
    }
}
