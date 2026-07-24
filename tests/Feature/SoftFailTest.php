<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request as McpRequest;
use Laravel\Nova\Fields\ActionFields;

it('translates a soft fail to HTTP 422 in the controller adapter', function (): void {
    $response = softFailingAction()->asController(Request::create('/', 'POST', ['value' => 'x']));
    $data = $response->getData(true);

    expect($response->status())->toBe(422)
        ->and($data['success'])->toBeFalse()
        ->and($data['message'])->toBe('Cannot proceed.')
        ->and($data['data']['reason_code'])->toBe(42);
});

it('translates a soft fail to an MCP error response', function (): void {
    $response = softFailingAction()->asMCPTool(new McpRequest(['value' => 'x']));

    expect($response->isError())->toBeTrue()
        ->and((string) $response->content())->toContain('Cannot proceed');
});

it('translates a soft fail to a Nova danger response', function (): void {
    $response = softFailingAction()->asNovaAction(
        new ActionFields(collect(['value' => 'x']), collect()),
        new Collection,
    );

    expect($response)->not->toBeNull();
});
