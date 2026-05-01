<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lorisleiva\Actions\Facades\Actions;
use Opscale\Actions\Tests\Fixtures\EchoAction;
use Workbench\App\Models\User;

uses(RefreshDatabase::class);

/*
 * The Action exposes 8 parameters; every one must be passed (even if empty)
 * so the command does not fall through to the interactive prompt path.
 */
beforeEach(function (): void {
    Actions::registerCommandsForAction(EchoAction::class);

    $this->cliArgs = fn (array $overrides = []): array => array_merge([
        'name' => 'cli-run',
        'count' => '3',
        'price' => '4.5',
        'active' => '1',
        'tags' => 'one',
        'status' => 'active',
        'user' => '',
        'payload' => '',
    ], $overrides);
});

it('runs the action via Artisan with all scalar argument types', function (): void {
    $this->artisan('echo-action', ($this->cliArgs)())
        ->assertSuccessful();
});

it('runs the action via Artisan with a model id as integer argument', function (): void {
    $user = User::factory()->create();

    $this->artisan('echo-action', ($this->cliArgs)([
        'name' => 'cli-with-user',
        'user' => (string) $user->getKey(),
    ]))
        ->assertSuccessful();
});

it('reports a validation error and exits non-zero when a required argument is empty', function (): void {
    $this->artisan('echo-action', ($this->cliArgs)(['name' => '']))
        ->expectsOutputToContain('name')
        ->assertFailed();
});

it('reports a validation error and exits non-zero when an enum is violated', function (): void {
    $this->artisan('echo-action', ($this->cliArgs)([
        'name' => 'x',
        'status' => 'unknown',
    ]))
        ->expectsOutputToContain('status')
        ->assertFailed();
});
