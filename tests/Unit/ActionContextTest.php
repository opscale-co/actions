<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Mcp\Request as McpRequest;
use Opscale\Actions\Tests\Fixtures\EchoAction;
use Workbench\App\Models\User;

uses(RefreshDatabase::class);

it('returns an empty array when no adapter has populated it', function (): void {
    $action = new EchoAction;

    expect($action->context())->toBe([]);
});

it('controller adapter populates context with the active request and user', function (): void {
    $user = User::factory()->create();
    $request = Request::create('/foo', 'POST', ['name' => 'x']);
    $request->setUserResolver(fn () => $user);

    $action = new EchoAction;
    $action->asController($request);

    $context = $action->context();

    expect($context)
        ->toHaveKey('request')
        ->and($context['request'])->toBeInstanceOf(Request::class)
        ->and($context['user']->is($user))->toBeTrue();
});

it('mcp adapter populates context with the active request', function (): void {
    $request = new McpRequest(['name' => 'x']);

    $action = new EchoAction;
    $action->asMCPTool($request);

    expect($action->context())
        ->toHaveKey('request')
        ->and($action->context()['request'])->toBeInstanceOf(McpRequest::class);
});
