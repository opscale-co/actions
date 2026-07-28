<?php

declare(strict_types=1);

namespace Opscale\Actions\Tests\Fixtures;

use Opscale\Actions\Action;

/**
 * Fixture Action declaring explicit Nova meta through `getActionMeta()`.
 */
final class CustomMetaAction extends Action
{
    #[\Override]
    public function identifier(): string
    {
        return 'custom-meta-action';
    }

    #[\Override]
    public function name(): string
    {
        return 'Custom Meta Action';
    }

    #[\Override]
    public function description(): string
    {
        return 'Declares Nova meta through getActionMeta().';
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
     * @return array<string, mixed>
     */
    final public function getActionMeta(): array
    {
        return ['detachedAction' => true, 'label' => 'Run now'];
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
