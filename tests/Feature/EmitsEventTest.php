<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Opscale\Actions\Tests\Fixtures\EmittingProbeAction;
use Opscale\Actions\Tests\Fixtures\SipocProbeAction;

it('dispatches a string-named event after a successful run when EmitsEvent is used', function (): void {
    Event::fake();

    (new EmittingProbeAction)->asController(Request::create('/', 'POST', ['value' => 'ok']));

    Event::assertDispatched('emitting-probe', function (string $event, array $payload): bool {
        return $event === 'emitting-probe'
            && $payload[0]['echoed'] === 'ok';
    });
});

it('does not dispatch any event when the action does not use EmitsEvent', function (): void {
    Event::fake();

    (new SipocProbeAction)->asController(Request::create('/', 'POST', ['value' => 'ok']));

    Event::assertNotDispatched('sipoc-probe');
});

it('does not dispatch on soft fail', function (): void {
    Event::fake();

    $action = new EmittingProbeAction;
    $action->handleImpl = fn (): array => $action->fail('nope');

    $action->asController(Request::create('/', 'POST', ['value' => 'x']));

    Event::assertNotDispatched('emitting-probe');
});

it('does not dispatch on canRun block', function (): void {
    Event::fake();

    $action = new EmittingProbeAction;
    $action->canRunResult = 'blocked';

    $action->asController(Request::create('/', 'POST', ['value' => 'x']));

    Event::assertNotDispatched('emitting-probe');
});

it('does not dispatch on hard fail', function (): void {
    Event::fake();

    $action = new EmittingProbeAction;
    $action->handleImpl = function (): array {
        throw new RuntimeException('exploded');
    };

    $action->asController(Request::create('/', 'POST', ['value' => 'x']));

    Event::assertNotDispatched('emitting-probe');
});

it('wildcard listener captures every action-completed event', function (): void {
    $seen = [];

    Event::listen('*', function (string $name, array $payload) use (&$seen): void {
        $seen[] = $name;
    });

    (new EmittingProbeAction)->asController(Request::create('/', 'POST', ['value' => 'ok']));

    expect($seen)->toContain('emitting-probe');
});
