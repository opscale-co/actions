<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request as McpRequest;
use Laravel\Nova\Fields\ActionFields;

it('translates a hard fail to HTTP 500 in the controller adapter', function (): void {
    $response = hardFailingAction()->asController(Request::create('/', 'POST', ['value' => 'x']));
    $data = $response->getData(true);

    expect($response->status())->toBe(500)
        ->and($data['success'])->toBeFalse()
        ->and($data['error'])->toBe('Boom!');
});

it('translates a hard fail to an MCP error response', function (): void {
    $response = hardFailingAction()->asMCPTool(new McpRequest(['value' => 'x']));

    expect($response->isError())->toBeTrue()
        ->and((string) $response->content())->toContain('Boom!');
});

it('translates a hard fail to a Nova danger response', function (): void {
    $response = hardFailingAction()->asNovaAction(
        new ActionFields(collect(['value' => 'x']), collect()),
        new Collection,
    );

    expect($response)->not->toBeNull();
});
