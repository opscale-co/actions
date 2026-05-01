<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Opscale\Actions\Tests\Fixtures\EchoAction;
use Workbench\App\Models\User;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Route::post('/test/echo', EchoAction::class);
});

it('invokes the action via HTTP POST and returns the result as JSON', function (): void {
    $response = $this->postJson('/test/echo', [
        'name' => 'order-1',
        'count' => 7,
        'price' => 19.95,
        'active' => true,
        'tags' => ['priority', 'rush'],
        'status' => 'active',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.received.name', 'order-1')
        ->assertJsonPath('data.received.count', 7)
        ->assertJsonPath('data.received.price', 19.95)
        ->assertJsonPath('data.received.active', true)
        ->assertJsonPath('data.received.tags', ['priority', 'rush'])
        ->assertJsonPath('data.received.status', 'active');
});

it('rejects an HTTP request that is missing a required field', function (): void {
    $response = $this->postJson('/test/echo', [
        'count' => 1,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonValidationErrors(['name']);
});

it('rejects an HTTP request that violates an enumerated rule', function (): void {
    $response = $this->postJson('/test/echo', [
        'name' => 'x',
        'status' => 'banana',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

it('rejects an HTTP request whose model reference does not exist', function (): void {
    $response = $this->postJson('/test/echo', [
        'name' => 'x',
        'user' => 99999,
    ]);

    $response->assertOk();
});

it('round-trips a model id through HTTP', function (): void {
    $user = User::factory()->create();

    $response = $this->postJson('/test/echo', [
        'name' => 'with-user',
        'user' => $user->getKey(),
    ]);

    $response->assertOk()
        ->assertJsonPath('data.received.user', $user->getKey());
});
