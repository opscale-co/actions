<?php

declare(strict_types=1);

namespace Opscale\Actions\Concerns;

/**
 * Behavioural trait — declares the Action's outputs go to a SIPOC "system"
 * client and owns the dispatch logic.
 *
 * When present, the pipeline calls `emitEvent()` after a successful
 * `handle()`, dispatching a string-named event built from the action's
 * identifier under the shared `opscale.actions` namespace:
 *
 *   event('opscale.actions.reset-password', [$outputs]);
 *
 * Consumers subscribe by the prefixed name (no filtering needed):
 *
 *   Event::listen('opscale.actions.reset-password', SendPasswordResetEmail::class);
 *   Event::listen('opscale.actions.*', AuditActionCompleted::class);
 *
 * No event is dispatched on soft fail (Result::fail or canRun() blocked) or
 * on hard fail (uncaught exception).
 */
trait EmitsEvent
{
    /**
     * Shared namespace prefixed onto every dispatched event name.
     */
    public const EVENT_PREFIX = 'opscale.actions';

    /**
     * Dispatch the system-client event for a successful run.
     *
     * @param  array<string, mixed>  $outputs  The validated handle() outputs.
     */
    public function emitEvent(string $identifier, array $outputs): void
    {
        event(self::EVENT_PREFIX.'.'.$identifier, [$outputs]);
    }
}
