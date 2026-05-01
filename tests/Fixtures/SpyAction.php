<?php

declare(strict_types=1);

namespace Opscale\Actions\Tests\Fixtures;

use Opscale\Actions\Action;

/**
 * Records the exact `$attributes` array that `handle()` receives, so tests
 * can assert which keys and values the adapter pipeline injected before
 * dispatching to the user-defined handler.
 */
final class SpyAction extends Action
{
    /** @var array<string, mixed>|null */
    public ?array $captured = null;

    public function identifier(): string
    {
        return 'spy-action';
    }

    public function name(): string
    {
        return 'Spy Action';
    }

    public function description(): string
    {
        return 'Captures whatever handle() receives so tests can assert it.';
    }

    /**
     * @return array<int, array{name: string, description: string, type: string, rules: array<int, mixed>}>
     */
    public function parameters(): array
    {
        return [
            [
                'name' => 'note',
                'description' => 'A throwaway free-text field.',
                'type' => 'string',
                'rules' => ['nullable', 'string'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function handle(array $attributes = []): array
    {
        $this->captured = $attributes;

        return ['message' => 'spied'];
    }
}
