<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\ActionFields;
use Opscale\Actions\Tests\Fixtures\SipocProbeAction;
use Workbench\App\Models\User;

uses(RefreshDatabase::class);

it('resolves prefill scalar values as before', function (): void {
    $action = new SipocProbeAction;
    $action->paramsSpec = [
        ['name' => 'value', 'type' => 'string', 'rules' => ['required', 'string']],
        ['name' => 'source', 'type' => 'string', 'rules' => ['required', 'string']],
    ];
    $action->outputsSpec = [
        ['name' => 'echoed', 'type' => 'string', 'rules' => ['required', 'string']],
        ['name' => 'source', 'type' => 'string', 'rules' => ['required', 'string']],
    ];
    $action->prefillSpec = ['source' => 'system'];
    $action->handleImpl = fn (array $inputs): array => $action->succeed([
        'echoed' => $inputs['value'],
        'source' => $inputs['source'],
    ]);

    $response = $action->asController(Request::create('/', 'POST', ['value' => 'x']));

    expect(data_get($response->getData(true), 'data.source'))->toBe('system');
});

it('executes closure prefill values with the controller adapter context (Request)', function (): void {
    $action = new SipocProbeAction;
    $action->paramsSpec = [
        ['name' => 'value', 'type' => 'string', 'rules' => ['required', 'string']],
        ['name' => 'ip', 'type' => 'string', 'rules' => ['required', 'string']],
    ];
    $action->outputsSpec = [
        ['name' => 'echoed', 'type' => 'string', 'rules' => ['required', 'string']],
        ['name' => 'ip', 'type' => 'string', 'rules' => ['required', 'string']],
    ];
    $action->prefillSpec = [
        'ip' => fn (Request $request): string => $request->ip() ?? 'unknown',
    ];
    $action->handleImpl = fn (array $inputs): array => $action->succeed([
        'echoed' => $inputs['value'],
        'ip' => $inputs['ip'],
    ]);

    $request = Request::create('/', 'POST', ['value' => 'x']);
    $request->server->set('REMOTE_ADDR', '10.0.0.42');

    $response = $action->asController($request);

    expect(data_get($response->getData(true), 'data.ip'))->toBe('10.0.0.42');
});

it('drops a null prefill closure return so the caller value survives', function (): void {
    $action = new SipocProbeAction;
    $action->paramsSpec = [
        ['name' => 'value', 'type' => 'string', 'rules' => ['required', 'string']],
    ];
    $action->outputsSpec = [
        ['name' => 'echoed', 'type' => 'string', 'rules' => ['required', 'string']],
    ];
    $action->prefillSpec = [
        // Closure returns null → dropped from resolved prefill → caller value wins.
        'value' => fn () => null,
    ];

    $response = $action->asController(Request::create('/', 'POST', ['value' => 'caller-value']));

    expect(data_get($response->getData(true), 'data.echoed'))->toBe('caller-value');
});

it('passes the Nova context object to the closure with `models`', function (): void {
    $user = User::factory()->create();

    $action = new SipocProbeAction;
    $action->paramsSpec = [
        ['name' => 'value', 'type' => 'string', 'rules' => ['required', 'string']],
        ['name' => 'email', 'type' => 'string', 'rules' => ['required', 'email']],
    ];
    $action->outputsSpec = [
        ['name' => 'email', 'type' => 'string', 'rules' => ['required', 'string']],
    ];
    $action->prefillSpec = [
        'email' => fn ($ctx) => $ctx->models->first()->email,
    ];
    $action->handleImpl = fn (array $inputs): array => $action->succeed(['email' => $inputs['email']]);

    $response = $action->asNovaAction(
        new ActionFields(collect(['value' => 'v']), collect()),
        collect([$user]),
    );

    expect($response)->not->toBeNull();
});
