<?php

declare(strict_types=1);

namespace Opscale\Actions\Tests\Fixtures;

use Closure;
use Opscale\Actions\Action;

/**
 * Configurable Action used by SIPOC tests. Each SIPOC concern (canRun,
 * outputs, soft/hard fail, options, prefill closures) can be tuned by
 * assigning callables or arrays to the public properties before calling.
 */
class SipocProbeAction extends Action
{
    /** @var array<int, array<string, mixed>> */
    public array $paramsSpec = [
        ['name' => 'value', 'description' => 'val', 'type' => 'string', 'rules' => ['required', 'string']],
    ];

    /** @var array<int, array<string, mixed>> */
    public array $outputsSpec = [
        ['name' => 'echoed', 'description' => 'echo', 'type' => 'string', 'rules' => ['required', 'string']],
    ];

    /** @var array<string, mixed> */
    public array $prefillSpec = [];

    /** @var array<string, array<int|string, string>> */
    public array $optionsSpec = [];

    public bool|string $canRunResult = true;

    /** @var Closure(array<string, mixed>): array<string, mixed> */
    public Closure $handleImpl;

    public function __construct()
    {
        $this->handleImpl = fn (array $inputs): array => $this->succeed(['echoed' => (string) ($inputs['value'] ?? '')]);
    }

    public function identifier(): string
    {
        return 'sipoc-probe';
    }

    public function name(): string
    {
        return 'SIPOC Probe';
    }

    public function description(): string
    {
        return 'Configurable action for SIPOC tests.';
    }

    public function parameters(): array
    {
        return $this->paramsSpec;
    }

    public function prefill(): array
    {
        return $this->prefillSpec;
    }

    public function options(): array
    {
        return $this->optionsSpec;
    }

    public function outputs(): array
    {
        return $this->outputsSpec;
    }

    public function canRun(array $inputs = []): bool|string
    {
        return $this->canRunResult;
    }

    public function handle(array $inputs = []): array
    {
        return ($this->handleImpl)($inputs);
    }
}
