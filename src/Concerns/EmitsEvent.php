<?php

declare(strict_types=1);

namespace Opscale\Actions\Concerns;

/**
 * Marker trait — declares the Action's outputs go to a SIPOC "system" client.
 *
 * When present, the pipeline dispatches a string-named event after a
 * successful `handle()`, named exactly after the action's identifier:
 *
 *   event($this->identifier(), [$outputs]);
 *
 * Consumers subscribe by identifier (no filtering needed):
 *
 *   Event::listen('reset-password', SendPasswordResetEmail::class);
 *   Event::listen('*', AuditActionCompleted::class);
 *
 * No event is dispatched on soft fail (Result::fail or canRun() blocked) or
 * on hard fail (uncaught exception).
 */
trait EmitsEvent
{
    //
}
