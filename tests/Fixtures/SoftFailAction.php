<?php

declare(strict_types=1);

namespace Opscale\Actions\Tests\Fixtures;

use Opscale\Actions\Action;

/**
 * Its `handle()` always returns `$this->fail(...)`, so the pipeline soft-fails
 * from inside the process. Used to prove `run()` propagates a handle-level
 * soft fail (message + partial data) into the returned `Result`.
 */
final class SoftFailAction extends Action
{
    public const REASON = 'Cannot proceed.';

    public function identifier(): string
    {
        return 'soft-fail';
    }

    public function name(): string
    {
        return 'Soft Fail';
    }

    public function description(): string
    {
        return 'Always soft-fails from handle(), used to test $this->fail().';
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
                'description' => 'Never produced on the fail path.',
                'type' => 'string',
                'rules' => ['required', 'string'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    public function handle(array $inputs = []): array
    {
        return $this->fail(self::REASON, ['reason_code' => 42]);
    }
}
