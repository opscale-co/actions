<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Opscale\Actions\Tests\Fixtures\EchoAction;
use Opscale\Actions\Tests\Fixtures\Payload;
use Workbench\App\Models\User;

uses(RefreshDatabase::class);

it('accepts a string', function (): void {
    $action = (new EchoAction)->fill(['name' => 'hello']);

    expect($action->name)->toBeString()->toBe('hello');
});

it('accepts an integer', function (): void {
    $action = (new EchoAction)->fill(['count' => 42]);

    expect($action->count)->toBeInt()->toBe(42);
});

it('accepts a float', function (): void {
    $action = (new EchoAction)->fill(['price' => 9.99]);

    expect($action->price)->toBeFloat()->toBe(9.99);
});

it('accepts a boolean', function (): void {
    $action = (new EchoAction)->fill(['active' => true]);

    expect($action->active)->toBeBool()->toBeTrue();
});

it('accepts an array', function (): void {
    $action = (new EchoAction)->fill(['tags' => ['a', 'b', 'c']]);

    expect($action->tags)->toBeArray()->toEqual(['a', 'b', 'c']);
});

it('accepts an Eloquent model instance', function (): void {
    $user = User::factory()->create();

    $action = (new EchoAction)->fill(['user' => $user]);

    expect($action->user)
        ->toBeInstanceOf(User::class)
        ->and($action->user->is($user))->toBeTrue();
});

it('accepts an arbitrary PHP value-object', function (): void {
    $payload = new Payload(reference: 'INV-1', weight: 7);

    $action = (new EchoAction)->fill(['payload' => $payload]);

    expect($action->payload)
        ->toBeInstanceOf(Payload::class)
        ->and($action->payload->reference)->toBe('INV-1')
        ->and($action->payload->weight)->toBe(7);
});

it('round-trips every type through the run() pipeline', function (): void {
    $user = User::factory()->create();
    $payload = new Payload(reference: 'X', weight: 1);

    // run() drives the full execute() pipeline and returns a Result. Note the
    // caller passes count => 3, but EchoAction::prefill() supplies count => 10,
    // and prefill wins — proving run() applies the same system-supplied values
    // every adapter does.
    $result = EchoAction::run([
        'name' => 'go',
        'count' => 3,
        'price' => 1.25,
        'active' => false,
        'tags' => ['x'],
        'status' => 'pending',
        'user' => $user,
        'payload' => $payload,
    ]);

    expect($result->isSuccess())->toBeTrue();

    $data = $result->data();

    expect($data['received']['name'])->toBe('go')
        ->and($data['received']['count'])->toBe(10)
        ->and($data['received']['price'])->toBe(1.25)
        ->and($data['received']['active'])->toBeFalse()
        ->and($data['received']['tags'])->toEqual(['x'])
        ->and($data['received']['status'])->toBe('pending')
        ->and($data['received']['user'])->toBeInstanceOf(User::class)
        ->and($data['received']['payload'])->toBeInstanceOf(Payload::class);

    expect($data['types'])->toEqual([
        'name' => 'string',
        'count' => 'int',
        'price' => 'float',
        'active' => 'bool',
        'tags' => 'array',
        'status' => 'string',
        'user' => User::class,
        'payload' => Payload::class,
    ]);
});

it('rejects an invalid string and reports a validation error', function (): void {
    $action = (new EchoAction)->fill(['name' => '']);

    expect(fn (): array => $action->validateAttributes())
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});
