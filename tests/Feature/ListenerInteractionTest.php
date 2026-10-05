<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Opscale\Actions\Results\Result;
use Opscale\Actions\Tests\Fixtures\ListeningProbeAction;
use Opscale\Actions\Tests\Fixtures\ProbeEvent;

beforeEach(function (): void {
    ListeningProbeAction::$log = [];
    ListeningProbeAction::$explode = false;
});

it('maps an object event payload against the action parameters', function (): void {
    (new ListeningProbeAction)->asListener(new ProbeEvent(user_id: 7, status: 'active'));

    expect(ListeningProbeAction::$log)->toHaveCount(1)
        ->and(ListeningProbeAction::$log[0])->toMatchArray(['user_id' => 7, 'status' => 'active']);
});

it('maps an associative array payload by key (EmitsEvent shape)', function (): void {
    // Mirrors event('opscale.actions.x', [['user_id' => 1, 'status' => 'ok']]).
    (new ListeningProbeAction)->asListener(['user_id' => 1, 'status' => 'ok']);

    expect(ListeningProbeAction::$log[0])->toMatchArray(['user_id' => 1, 'status' => 'ok']);
});

it('maps positional scalar arguments in declared parameter order', function (): void {
    (new ListeningProbeAction)->asListener(5, 'done');

    expect(ListeningProbeAction::$log[0])->toMatchArray(['user_id' => 5, 'status' => 'done']);
});

it('discards payload keys that are not declared parameters', function (): void {
    (new ListeningProbeAction)->asListener(['user_id' => 3, 'status' => 'ok', 'extra' => 'ignored']);

    expect(ListeningProbeAction::$log[0])->toBe(['user_id' => 3, 'status' => 'ok']);
});

it('returns a successful Result', function (): void {
    $result = (new ListeningProbeAction)->asListener(new ProbeEvent(user_id: 7, status: 'active'));

    expect($result)->toBeInstanceOf(Result::class)
        ->and($result->isFail())->toBeFalse()
        ->and($result->data())->toMatchArray(['user_id' => 7, 'status' => 'active']);
});

it('logs and does not throw on validation failure, returning a failed Result', function (): void {
    Log::spy();

    // Missing `status` → validation fails inside the pipeline.
    $result = (new ListeningProbeAction)->asListener(['user_id' => 9]);

    expect($result->isFail())->toBeTrue()
        ->and(ListeningProbeAction::$log)->toBeEmpty();

    Log::shouldHaveReceived('warning')->once();
});

it('logs and does not throw on a hard failure (exception in handle)', function (): void {
    Log::spy();
    ListeningProbeAction::$explode = true;

    $result = (new ListeningProbeAction)->asListener(new ProbeEvent(user_id: 1, status: 'ok'));

    expect($result->isFail())->toBeTrue()
        ->and($result->message())->toBe('exploded');

    Log::shouldHaveReceived('error')->once();
});

it('reports shouldQueue from the $shouldQueueListener property', function (): void {
    $action = new ListeningProbeAction;

    expect($action->shouldQueue())->toBeFalse();

    $action->shouldQueueListener = true;

    expect($action->shouldQueue())->toBeTrue();
});

it('runs through lorisleiva listener wiring when registered for an event object', function (): void {
    Event::listen(ProbeEvent::class, ListeningProbeAction::class);

    event(new ProbeEvent(user_id: 42, status: 'wired'));

    expect(ListeningProbeAction::$log)->toHaveCount(1)
        ->and(ListeningProbeAction::$log[0])->toMatchArray(['user_id' => 42, 'status' => 'wired']);
});

it('runs through lorisleiva listener wiring for a string-named event payload', function (): void {
    Event::listen('opscale.actions.some-producer', ListeningProbeAction::class);

    event('opscale.actions.some-producer', [['user_id' => 11, 'status' => 'from-string']]);

    expect(ListeningProbeAction::$log[0])->toMatchArray(['user_id' => 11, 'status' => 'from-string']);
});
