<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Opscale\Actions\Tests\Fixtures\EchoAction;
use Workbench\App\Models\User;

uses(RefreshDatabase::class);

it('invokes the action through the MCP tool adapter and echoes every type back', function (): void {
    $request = new Request([
        'name' => 'mcp-run',
        'count' => 5,
        'price' => 12.34,
        'active' => true,
        'tags' => ['x', 'y'],
        'status' => 'pending',
    ]);

    $response = (new EchoAction)->asMCPTool($request);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->isError())->toBeFalse();

    $payload = json_decode((string) $response->content(), true);

    expect($payload['received']['name'])->toBe('mcp-run')
        ->and($payload['received']['count'])->toBe(5)
        ->and($payload['received']['price'])->toBe(12.34)
        ->and($payload['received']['active'])->toBeTrue()
        ->and($payload['received']['tags'])->toEqual(['x', 'y'])
        ->and($payload['received']['status'])->toBe('pending');
});

it('forwards a model id through the MCP tool adapter', function (): void {
    $user = User::factory()->create();

    $request = new Request([
        'name' => 'mcp-user',
        'user' => $user->getKey(),
    ]);

    $response = (new EchoAction)->asMCPTool($request);

    expect($response->isError())->toBeFalse();

    $payload = json_decode((string) $response->content(), true);

    expect($payload['received']['user'])->toBe($user->getKey());
});

it('returns a validation error response when a required field is missing', function (): void {
    $request = new Request([
        'count' => 1,
    ]);

    $response = (new EchoAction)->asMCPTool($request);

    expect($response->isError())->toBeTrue()
        ->and((string) $response->content())->toContain('name');
});

it('returns a validation error response when an enum is violated', function (): void {
    $request = new Request([
        'name' => 'x',
        'status' => 'unknown',
    ]);

    $response = (new EchoAction)->asMCPTool($request);

    expect($response->isError())->toBeTrue()
        ->and((string) $response->content())->toContain('status');
});
