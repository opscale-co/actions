<?php

namespace Opscale\Actions\Concerns;

use Laravel\Mcp\Response;

/**
 * Trait AsMCPTool
 *
 * Provides MCP tool functionality for standalone action classes.
 * Use this trait when you want to use an action as an MCP tool
 * without extending the base Action class.
 *
 * This trait should be used alongside AsAction from laravel-actions.
 *
 * @see \Opscale\Actions\Decorators\MCPToolDecorator
 * @see \Opscale\Actions\DesignPatterns\MCPToolDesignPattern
 */
trait AsMCPTool
{
    /**
     * Whether the tool should be registered.
     */
    public bool $shouldRegisterTool = true;

    /**
     * Return a text response.
     */
    public function text(string $text): Response
    {
        return Response::text($text);
    }

    /**
     * Return a JSON response.
     */
    public function json(array $data): Response
    {
        return Response::text(json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Return an error response.
     */
    public function error(string $message): Response
    {
        return Response::error($message);
    }

    /**
     * Return a resource response.
     */
    public function resource(string $uri, string $text, ?string $mimeType = null): Response
    {
        return Response::resource($uri, $text, $mimeType);
    }

    /**
     * Return an image response.
     */
    public function image(string $data, string $mimeType = 'image/png'): Response
    {
        return Response::image($data, $mimeType);
    }
}
