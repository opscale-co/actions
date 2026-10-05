<?php

declare(strict_types=1);

namespace Opscale\Actions\Tests\Fixtures;

/**
 * Plain event object whose public properties are named to match the listener
 * action's parameters(), used to assert object-payload normalization.
 */
final class ProbeEvent
{
    public function __construct(
        public readonly int $user_id,
        public readonly string $status,
    ) {}
}
