<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Opscale\Actions\Tests\Fixtures\SipocProbeAction;

it('accepts outputs that satisfy the outputs() schema', function (): void {
    $action = new SipocProbeAction;
    $action->handleImpl = fn (array $inputs): array => $action->succeed(['echoed' => 'ok']);

    $response = $action->asController(Request::create('/', 'POST', ['value' => 'x']));

    expect($response->status())->toBe(200);
});

it('re-throws as hard fail when handle() returns outputs that violate outputs() rules', function (): void {
    $action = new SipocProbeAction;
    $action->outputsSpec = [
        ['name' => 'echoed', 'type' => 'string', 'rules' => ['required', 'string']],
        ['name' => 'count', 'type' => 'integer', 'rules' => ['required', 'integer']],
    ];
    // Missing the required `count` key.
    $action->handleImpl = fn (array $inputs): array => $action->succeed(['echoed' => 'ok']);

    $response = $action->asController(Request::create('/', 'POST', ['value' => 'x']));
    $data = $response->getData(true);

    expect($response->status())->toBe(422)
        ->and($data['errors'])->toHaveKey('count');
});

it('skips outputs validation when the schema is empty', function (): void {
    $action = new SipocProbeAction;
    $action->outputsSpec = [];
    $action->handleImpl = fn (array $inputs): array => $action->succeed(['anything' => 'goes']);

    $response = $action->asController(Request::create('/', 'POST', ['value' => 'x']));

    expect($response->status())->toBe(200);
});
