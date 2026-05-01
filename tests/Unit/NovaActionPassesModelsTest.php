<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\ActionFields;
use Opscale\Actions\Tests\Fixtures\SpyAction;
use Workbench\App\Models\User;

uses(RefreshDatabase::class);

it('passes a single selected model into handle() under a singular snake_case key', function (): void {
    $user = User::factory()->create(['name' => 'Alice']);
    $action = new SpyAction;

    $action->asNovaAction(
        new ActionFields(collect(['note' => 'one']), collect()),
        collect([$user]),
    );

    expect($action->captured)
        ->toHaveKey('user')
        ->and($action->captured['user'])->toBeInstanceOf(Collection::class)
        ->and($action->captured['user'])->toHaveCount(1)
        ->and($action->captured['user']->first()->is($user))->toBeTrue();
});

it('passes multiple selected models into handle() under the plural snake_case key', function (): void {
    $alice = User::factory()->create(['name' => 'Alice']);
    $bob = User::factory()->create(['name' => 'Bob']);
    $action = new SpyAction;

    $action->asNovaAction(
        new ActionFields(collect(['note' => 'many']), collect()),
        collect([$alice, $bob]),
    );

    expect($action->captured)
        ->toHaveKey('users')
        ->and($action->captured['users'])->toBeInstanceOf(Collection::class)
        ->and($action->captured['users'])->toHaveCount(2)
        ->and($action->captured['users']->pluck('name')->all())->toEqual(['Alice', 'Bob']);
});

it('still forwards the action-form fields alongside the injected models', function (): void {
    $user = User::factory()->create();
    $action = new SpyAction;

    $action->asNovaAction(
        new ActionFields(collect(['note' => 'hello']), collect()),
        collect([$user]),
    );

    expect($action->captured['note'])->toBe('hello')
        ->and($action->captured)->toHaveKeys(['note', 'user']);
});

it('does not inject any model key when the action runs against zero models', function (): void {
    $action = new SpyAction;

    $action->asNovaAction(
        new ActionFields(collect(['note' => 'standalone']), collect()),
        collect(),
    );

    expect($action->captured)
        ->toHaveKey('note')
        ->and($action->captured['note'])->toBe('standalone')
        // The empty-string key is what `resolveParameterLabel()` returns
        // for an empty collection — the adapter still sets it, so document
        // the current contract precisely.
        ->and(array_key_exists('user', $action->captured))->toBeFalse()
        ->and(array_key_exists('users', $action->captured))->toBeFalse();
});
