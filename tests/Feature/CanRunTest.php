<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\ActionFields;
use Opscale\Actions\Tests\Fixtures\SipocProbeAction;

it('runs the action when canRun() returns true', function (): void {
    $action = new SipocProbeAction;
    $action->canRunResult = true;

    $data = canRunResponse($action, ['value' => 'ok']);

    expect($data['success'])->toBeTrue()
        ->and($data['data']['echoed'])->toBe('ok');
});

it('soft-fails with the generic message when canRun() returns false', function (): void {
    $action = new SipocProbeAction;
    $action->canRunResult = false;

    $response = $action->asController(Request::create('/', 'POST', ['value' => 'anything']));
    $data = $response->getData(true);

    expect($response->status())->toBe(422)
        ->and($data['success'])->toBeFalse()
        ->and($data['message'])->toBe('This action cannot be executed.');
});

it('soft-fails with the provided reason when canRun() returns a string', function (): void {
    $action = new SipocProbeAction;
    $action->canRunResult = 'Insufficient permissions.';

    $response = $action->asController(Request::create('/', 'POST', ['value' => 'anything']));

    expect($response->status())->toBe(422)
        ->and($response->getData(true)['message'])->toBe('Insufficient permissions.');
});

it('renders canRun() failure as a Nova danger action', function (): void {
    $action = new SipocProbeAction;
    $action->canRunResult = 'Not allowed.';

    $response = $action->asNovaAction(
        new ActionFields(collect(['value' => 'v']), collect()),
        new Collection,
    );

    expect($response)->not->toBeNull();
});
