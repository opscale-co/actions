<?php

declare(strict_types=1);

namespace Opscale\Actions\Tests\Fixtures;

use Opscale\Actions\Action;

/**
 * Its `canRun()` gate always returns a string reason, so the pipeline
 * soft-fails before `handle()` runs. Used to prove `run()` surfaces a gate
 * block as a failed `Result` carrying the reason — with no fresh-instance
 * tuning needed (static `run()` builds its own instance).
 */
final class GateBlockedAction extends Action
{
    public const REASON = 'Blocked by the gate.';

    public function identifier(): string
    {
        return 'gate-blocked';
    }

    public function name(): string
    {
        return 'Gate Blocked';
    }

    public function description(): string
    {
        return 'Always blocked by canRun(), used to test soft-fail via the gate.';
    }

    /**
     * @return array<int, array{name: string, description: string, type: string, rules: array<int, mixed>}>
     */
    public function parameters(): array
    {
        return [
            [
                'name' => 'value',
                'description' => 'Any string.',
                'type' => 'string',
                'rules' => ['required', 'string'],
            ],
        ];
    }

    /**
     * @return array<int, array{name: string, description: string, type: string, rules: array<int, mixed>}>
     */
    public function outputs(): array
    {
        return [
            [
                'name' => 'echoed',
                'description' => 'Never produced.',
                'type' => 'string',
                'rules' => ['required', 'string'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    public function canRun(array $inputs = []): bool|string
    {
        return self::REASON;
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    public function handle(array $inputs = []): array
    {
        return $this->succeed(['echoed' => (string) ($inputs['value'] ?? '')]);
    }
}
