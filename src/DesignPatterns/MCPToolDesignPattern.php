<?php

namespace Opscale\Actions\DesignPatterns;

use Laravel\Mcp\Server\ServerContext;
use Lorisleiva\Actions\BacktraceFrame;
use Lorisleiva\Actions\DesignPatterns\DesignPattern;
use Opscale\Actions\Concerns\AsMCPTool;
use Opscale\Actions\Decorators\MCPToolDecorator;

class MCPToolDesignPattern extends DesignPattern
{
    /**
     * Track which classes have been extended for MCP.
     */
    protected static array $extended = [];

    public function getTrait(): string
    {
        return AsMCPTool::class;
    }

    public function recognizeFrame(BacktraceFrame $frame): bool
    {
        // MCP tools are resolved when ServerContext::tools() is called
        // This happens during both listing (ListTools) and execution (CallTool)
        if ($frame->function !== 'resolvePrimitives') {
            return false;
        }

        $object = $frame->getObject();

        if ($object === null) {
            return false;
        }

        // Check if the object is a ServerContext instance
        return $object instanceof ServerContext;
    }

    public function decorate($instance, BacktraceFrame $frame)
    {
        return app(MCPToolDecorator::class, [
            'action' => $instance,
        ]);
    }
}
