<?php

declare(strict_types=1);

namespace Opscale\Actions\Tests\Fixtures;

use Opscale\Actions\Action;
use RuntimeException;

/**
 * Action used to exercise the ListenerAdapter. Captures the inputs its
 * handle() received into a static log so assertions survive the fresh
 * instance Laravel resolves when the Action is dispatched as a listener.
 */
class ListeningProbeAction extends Action
{
    /** @var array<int, array<string, mixed>> */
    public static array $log = [];

    public static bool $explode = false;

    public function identifier(): string
    {
        return 'listening-probe';
    }

    public function name(): string
    {
        return 'Listening Probe';
    }

    public function description(): string
    {
        return 'Action used to test the listener adapter.';
    }

    public function parameters(): array
    {
        return [
            ['name' => 'user_id', 'description' => 'user', 'type' => 'integer', 'rules' => ['required', 'integer']],
            ['name' => 'status', 'description' => 'status', 'type' => 'string', 'rules' => ['required', 'string']],
        ];
    }

    public function outputs(): array
    {
        return [
            ['name' => 'user_id', 'description' => 'user', 'type' => 'integer', 'rules' => ['required', 'integer']],
            ['name' => 'status', 'description' => 'status', 'type' => 'string', 'rules' => ['required', 'string']],
        ];
    }

    public function handle(array $inputs = []): array
    {
        if (self::$explode) {
            throw new RuntimeException('exploded');
        }

        self::$log[] = $inputs;

        return $this->succeed([
            'user_id' => (int) $inputs['user_id'],
            'status' => (string) $inputs['status'],
        ]);
    }
}
