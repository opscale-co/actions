<?php

declare(strict_types=1);

use Opscale\Actions\Tests\Fixtures\EchoAction;
use Opscale\Actions\Tests\Fixtures\Payload;
use Workbench\App\Models\User;

it('exposes a parameter schema entry for every input field', function (): void {
    $action = new EchoAction;

    $names = collect($action->parameters())->pluck('name')->all();

    expect($names)->toEqual([
        'name', 'count', 'price', 'active', 'tags', 'status', 'user', 'payload',
    ]);
});

it('declares the expected PHP types per parameter', function (): void {
    $action = new EchoAction;

    $types = collect($action->parameters())->pluck('type', 'name')->all();

    expect($types)->toEqual([
        'name' => 'string',
        'count' => 'integer',
        'price' => 'float',
        'active' => 'boolean',
        'tags' => 'array',
        'status' => 'string',
        'user' => User::class,
        'payload' => Payload::class,
    ]);
});

it('rules() converts the parameter schema into a Laravel validation array', function (): void {
    $action = new EchoAction;

    $rules = $action->rules();

    expect($rules)
        ->toHaveKeys(['name', 'count', 'price', 'active', 'tags', 'status', 'user', 'payload'])
        ->and($rules['name'])->toContain('required', 'string', 'max:50')
        ->and($rules['count'])->toContain('nullable', 'integer', 'between:0,1000')
        ->and($rules['status'])->toContain('in:active,inactive,pending');
});

it('marks the schema entries that target non-scalar PHP classes', function (): void {
    $action = new EchoAction;

    $classBacked = collect($action->parameters())
        ->filter(fn (array $p): bool => class_exists($p['type']))
        ->pluck('name')
        ->all();

    expect($classBacked)->toEqual(['user', 'payload']);
});
