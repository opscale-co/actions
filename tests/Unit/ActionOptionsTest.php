<?php

declare(strict_types=1);

use Opscale\Actions\Action;
use Opscale\Actions\Tests\Fixtures\EchoAction;

it('returns an empty array when no options are declared', function (): void {
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
            return 'No options.';
        }

        public function parameters(): array
        {
            return [];
        }

        public function handle(array $attributes = []): array
        {
            return [];
        }
    };

    expect($bare->options())->toBe([]);
});

it('exposes choice lists keyed by parameter name', function (): void {
    $options = (new EchoAction)->options();

    expect($options)
        ->toHaveKey('status')
        ->and($options['status'])->toEqual(['active', 'inactive', 'pending']);
});

it('only declares options entries for parameters that opt into one', function (): void {
    $action = new EchoAction;
    $declared = array_keys($action->options());
    $params = collect($action->parameters())->pluck('name')->all();

    foreach ($declared as $key) {
        expect($params)->toContain($key);
    }
});
