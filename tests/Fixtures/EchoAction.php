<?php

declare(strict_types=1);

namespace Opscale\Actions\Tests\Fixtures;

use Opscale\Actions\Action;
use Workbench\App\Models\User;

/**
 * Test fixture Action that exercises every parameter type the package supports
 * — string, integer, float, boolean, array, plain PHP object, and Eloquent model.
 *
 * `handle()` echoes the validated input back so each interaction-form test
 * can assert the round-trip.
 */
final class EchoAction extends Action
{
    public function identifier(): string
    {
        return 'echo-action';
    }

    public function name(): string
    {
        return 'Echo Action';
    }

    public function description(): string
    {
        return 'Echoes the inputs back, used to test every interaction surface.';
    }

    /**
     * @return array<int, array{name: string, description: string, type: string, rules: array<int, mixed>}>
     */
    public function parameters(): array
    {
        return [
            [
                'name' => 'name',
                'description' => 'A free-text label.',
                'type' => 'string',
                'rules' => ['required', 'string', 'max:50'],
            ],
            [
                'name' => 'count',
                'description' => 'An integer quantity.',
                'type' => 'integer',
                'rules' => ['nullable', 'integer', 'between:0,1000'],
            ],
            [
                'name' => 'price',
                'description' => 'A floating-point price.',
                'type' => 'float',
                'rules' => ['nullable', 'numeric'],
            ],
            [
                'name' => 'active',
                'description' => 'Whether the record is active.',
                'type' => 'boolean',
                'rules' => ['nullable', 'boolean'],
            ],
            [
                'name' => 'tags',
                'description' => 'An array of free-form tags.',
                'type' => 'array',
                'rules' => ['nullable', 'array'],
            ],
            [
                'name' => 'status',
                'description' => 'A constrained enumeration.',
                'type' => 'string',
                'rules' => ['nullable', 'string', 'in:active,inactive,pending'],
            ],
            [
                'name' => 'user',
                'description' => 'A reference to a User Eloquent model.',
                'type' => User::class,
                'rules' => ['nullable'],
            ],
            [
                'name' => 'payload',
                'description' => 'An arbitrary PHP value-object.',
                'type' => Payload::class,
                'rules' => ['nullable'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function prefill(): array
    {
        return [
            'count' => 10,
            'tags' => ['a', 'b', 'c'],
            'status' => ['active', 'inactive', 'pending'],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function handle(array $attributes = []): array
    {
        return [
            'received' => $attributes,
            'types' => array_map(fn ($v): string => get_debug_type($v), $attributes),
        ];
    }
}
