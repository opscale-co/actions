<?php

declare(strict_types=1);

namespace Opscale\Actions\Tests\Fixtures;

use Opscale\Actions\Action;

/**
 * Fixture Action declaring Nova meta through the `$actionMeta` property.
 */
final class PropertyMetaAction extends Action
{
    /** @var array<string, mixed> */
    public array $actionMeta = ['icon' => 'lock-closed'];

    #[\Override]
    public function identifier(): string
    {
        return 'property-meta-action';
    }

    #[\Override]
    public function name(): string
    {
        return 'Property Meta Action';
    }

    #[\Override]
    public function description(): string
    {
        return 'Declares Nova meta through the actionMeta property.';
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
