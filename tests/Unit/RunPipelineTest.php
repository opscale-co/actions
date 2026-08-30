<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Opscale\Actions\Results\Result;
use Opscale\Actions\Tests\Fixtures\EmittingProbeAction;
use Opscale\Actions\Tests\Fixtures\GateBlockedAction;
use Opscale\Actions\Tests\Fixtures\SipocProbeAction;
use Opscale\Actions\Tests\Fixtures\SoftFailAction;

/**
 * run() overrides Lorisleiva\Actions\Concerns\AsObject::run() so that calling
 * an Action programmatically drives the full SIPOC execute() pipeline and
 * returns a Result — enabling adapter-free unit testing.
 */
it('returns a successful Result carrying handle() output', function (): void {
    $result = SipocProbeAction::run(['value' => 'ok']);

    expect($result)->toBeInstanceOf(Result::class)
        ->and($result->isSuccess())->toBeTrue()
        ->and($result->isFail())->toBeFalse()
        ->and($result->message())->toBeNull()
        ->and($result->data())->toBe(['echoed' => 'ok']);
});

it('soft-fails with the gate reason when canRun() blocks', function (): void {
    $result = GateBlockedAction::run(['value' => 'ok']);

    expect($result->isFail())->toBeTrue()
        ->and($result->message())->toBe(GateBlockedAction::REASON)
        ->and($result->data())->toBe([]);
});

it('soft-fails with the message and partial data when handle() calls fail()', function (): void {
    $result = SoftFailAction::run(['value' => 'ok']);

    expect($result->isFail())->toBeTrue()
        ->and($result->message())->toBe(SoftFailAction::REASON)
        ->and($result->data())->toBe(['reason_code' => 42]);
});

it('validates inputs against parameters() rules', function (): void {
    expect(fn (): Result => SipocProbeAction::run([]))
        ->toThrow(ValidationException::class);
});

it('dispatches the system event on success through run()', function (): void {
    Event::fake();

    $result = EmittingProbeAction::run(['value' => 'ok']);

    expect($result->isSuccess())->toBeTrue();

    Event::assertDispatched('emitting-probe', function (string $event, array $payload): bool {
        return $payload[0]['echoed'] === 'ok';
    });
});

it('does not dispatch any event on a gate soft-fail', function (): void {
    Event::fake();

    GateBlockedAction::run(['value' => 'ok']);

    Event::assertNotDispatched('gate-blocked');
});
