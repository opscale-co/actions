<?php

declare(strict_types=1);

namespace Opscale\Actions\Tests\Fixtures;

/**
 * Plain PHP value-object used to assert that an arbitrary object can travel
 * through the Action pipeline as a parameter (without being a Model).
 */
final class Payload
{
    public function __construct(
        public readonly string $reference,
        public readonly int $weight,
    ) {}
}
