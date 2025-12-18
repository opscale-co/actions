<?php

namespace Opscale\Actions\Decorators;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Lorisleiva\Actions\Concerns\DecorateActions;

class MCPToolDecorator extends Tool
{
    use DecorateActions;

    /**
     * Create a new MCP tool decorator instance.
     */
    public function __construct($action)
    {
        $this->setAction($action);

        $className = class_basename($action);
        $name = Str::slug(Str::snake($className));
        $title = Str::headline($className);

        $this->name = $this->fromActionMethodOrProperty('getToolName', 'toolName', $name);
        $this->title = $this->fromActionMethodOrProperty('getToolTitle', 'toolTitle', $title);
        $this->description = $this->fromActionMethodOrProperty('getToolDescription', 'toolDescription');

        if ($this->description === null) {
            throw new InvalidArgumentException(
                sprintf('Action [%s] must define a tool description via getToolDescription() method or $toolDescription property.', $className)
            );
        }
    }

    /**
     * Define the input schema for the tool.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return $this->fromActionMethodOrProperty('getToolSchema', 'toolSchema', [], [$schema]);
    }

    /**
     * Determine if the tool should be registered.
     */
    public function shouldRegister(Request $request): bool
    {
        return $this->fromActionMethodOrProperty('getShouldRegisterTool', 'shouldRegisterTool', true);
    }

    /**
     * Handle the tool execution.
     *
     * This method is called by the MCP server when the tool is invoked.
     * Similar to ControllerDecorator, it passes the Request to asMCPTool
     * or an array of arguments to handle.
     *
     * @return Response|iterable<Response>
     */
    public function handle(Request $request): Response|iterable
    {
        // Call asMCPTool if it exists, passing the Request
        if ($this->hasMethod('asMCPTool')) {
            return $this->resolveAndCallMethod('asMCPTool', ['request' => $request]);
        }

        // Fall back to handle, passing the arguments array
        if ($this->hasMethod('handle')) {
            $arguments = $request->toArray() ?? [];
            $result = $this->resolveAndCallMethod('handle', $arguments);

            if (empty($result)) {
                return Response::error('Something went wrong while executing the tool.');
            }

            return Response::text(json_encode($result, JSON_PRETTY_PRINT));
        }
    }
}
