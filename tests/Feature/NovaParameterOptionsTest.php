<?php

declare(strict_types=1);

use Laravel\Nova\Fields\MultiSelect;
use Laravel\Nova\Fields\Select;
use Opscale\Actions\Tests\Fixtures\Priority;
use Opscale\Actions\Tests\Fixtures\SipocProbeAction;

it('renders a per-parameter options array as a Select with the value-label map', function (): void {
    $action = new SipocProbeAction;
    $action->paramsSpec = [
        ['name' => 'status', 'type' => 'string', 'rules' => ['required', 'string'], 'options' => ['a' => 'Alpha', 'b' => 'Beta']],
    ];

    $field = $action->getActionFields()[0];

    expect($field)->toBeInstanceOf(Select::class)
        ->and(value($field->optionsCallback))->toBe(['a' => 'Alpha', 'b' => 'Beta']);
});

it('renders a per-parameter options spec on an array parameter as a MultiSelect', function (): void {
    $action = new SipocProbeAction;
    $action->paramsSpec = [
        ['name' => 'channels', 'type' => 'array', 'rules' => ['required', 'array'], 'options' => ['x' => 'X', 'y' => 'Y']],
    ];

    $field = $action->getActionFields()[0];

    expect($field)->toBeInstanceOf(MultiSelect::class)
        ->and(value($field->optionsCallback))->toBe(['x' => 'X', 'y' => 'Y']);
});

it('resolves a backed-enum class-string into value => name options', function (): void {
    $action = new SipocProbeAction;
    $action->paramsSpec = [
        ['name' => 'priority', 'type' => 'string', 'rules' => ['required', 'string'], 'options' => Priority::class],
    ];

    $field = $action->getActionFields()[0];

    expect($field)->toBeInstanceOf(Select::class)
        ->and(value($field->optionsCallback))->toBe(['low' => 'Low', 'high' => 'High']);
});

it('does not invoke a Closure options spec while building the fields', function (): void {
    $called = false;

    $action = new SipocProbeAction;
    $action->paramsSpec = [
        ['name' => 'interviewer_id', 'type' => 'string', 'rules' => ['required', 'ulid'], 'options' => function () use (&$called): array {
            $called = true;

            return ['01A' => 'Ana'];
        }],
    ];

    $field = $action->getActionFields()[0];

    expect($called)->toBeFalse()
        ->and(value($field->optionsCallback))->toBe(['01A' => 'Ana'])
        ->and($called)->toBeTrue();
});

it('prefers the options() map over the per-parameter options spec', function (): void {
    $action = new SipocProbeAction;
    $action->paramsSpec = [
        ['name' => 'status', 'type' => 'string', 'rules' => ['required', 'string'], 'options' => ['x' => 'X']],
    ];
    $action->optionsSpec = ['status' => ['active', 'inactive']];

    $field = $action->getActionFields()[0];

    expect($field)->toBeInstanceOf(Select::class)
        ->and(value($field->optionsCallback))->toBe(['active', 'inactive']);
});

it('rejects an invalid per-parameter options spec', function (): void {
    $action = new SipocProbeAction;
    $action->paramsSpec = [
        ['name' => 'status', 'type' => 'string', 'rules' => ['required', 'string'], 'options' => 42],
    ];

    expect(fn (): array => $action->getActionFields())
        ->toThrow(InvalidArgumentException::class);
});
