<?php

declare(strict_types=1);

use Opscale\Actions\Action;
use Opscale\Actions\Tests\Fixtures\EchoAction;

it('returns an empty array when no defaults are declared', function (): void {
    $bare = new class extends Action
    {
        public function identifier(): string
        {
            return 'bare';
        }

        public function name(): string
        {
            return 'Bare';
        }

        public function description(): string
        {
            return 'No prefill.';
        }

        public function parameters(): array
        {
            return [];
        }

        public function outputs(): array
        {
            return [];
        }

        public function handle(array $attributes = []): array
        {
            return [];
        }
    };

    expect($bare->prefill())->toBe([]);
});

it('exposes scalar defaults keyed by parameter name', function (): void {
    $prefill = (new EchoAction)->prefill();

    expect($prefill)
        ->toHaveKey('count')
        ->and($prefill['count'])->toBeInt()->toBe(10);
});

it('does not leak options data into prefill anymore', function (): void {
    $prefill = (new EchoAction)->prefill();

    // status / tags belong to options() now, not prefill.
    expect($prefill)
        ->not->toHaveKey('status')
        ->not->toHaveKey('tags');
});

it('only declares prefill entries for parameters that opt into one', function (): void {
    $action = new EchoAction;
    $declared = array_keys($action->prefill());
    $params = collect($action->parameters())->pluck('name')->all();

    foreach ($declared as $key) {
        expect($params)->toContain($key);
    }
});
