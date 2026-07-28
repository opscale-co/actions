<?php

declare(strict_types=1);

namespace Opscale\Actions\Tests\Fixtures;

use Opscale\Actions\Action;

/**
 * Minimal fixture Action declaring no Nova meta at all, used to assert that
 * NovaActionDecorator adds nothing when the wrapped action defines neither
 * `getActionMeta()` nor `$actionMeta`.
 */
final class MetaProbeAction extends Action
{
    #[\Override]
    public function identifier(): string
    {
        return 'meta-probe-action';
    }

    #[\Override]
    public function name(): string
    {
        return 'Meta Probe Action';
    }

    #[\Override]
    public function description(): string
    {
        return 'Probes Nova meta forwarding.';
    }

    /**
     * @return array<int, array{name: string, description: string, type: string, rules: array<int, mixed>}>
     */
    #[\Override]
    public function parameters(): array
    {
        return [];
    }

    /**
     * @return array<int, array{name: string, description: string, type: string, rules: array<int, mixed>}>
     */
    #[\Override]
    public function outputs(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    #[\Override]
    public function handle(array $inputs = []): array
    {
        return $this->succeed([]);
    }
}
