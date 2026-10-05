<?php

declare(strict_types=1);

namespace Opscale\Actions\Adapters;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsListener;
use Lorisleiva\Actions\Decorators\ListenerDecorator;
use Opscale\Actions\Results\Result;
use Throwable;

/**
 * Trait ListenerAdapter
 *
 * Adapts the Action contract to an event listener via the SIPOC pipeline. The
 * symmetric inverse of EmitsEvent: where EmitsEvent makes an Action a producer
 * of `opscale.actions.{identifier}` events, this lets an Action BE a consumer
 * of any event, registered the vanilla Laravel way:
 *
 *   Event::listen(SomeEvent::class, SomeAction::class);
 *   Event::listen('opscale.actions.reset-password', SendWelcomeEmail::class);
 *
 * Lorisleiva's ListenerDecorator detects `asListener()` (resolution order:
 * asListener → handle) and calls it with the dispatched payload; it also
 * consults `shouldQueue()` on the queued path.
 *
 * The incoming payload is normalized and mapped against the Action's declared
 * parameters() before the pipeline runs:
 *   - an event object      → its public props / toArray() / Arrayable
 *   - an associative array  → matched by key
 *   - positional/scalar args → matched in declared parameter order
 *
 *   asListener()  → execute()  (event payload as inputs + prefill context)
 *   shouldQueue() → $shouldQueueListener
 *
 * @see AsListener
 * @see ListenerDecorator
 */
trait ListenerAdapter
{
    /**
     * Whether this Action should be queued when dispatched as a listener.
     * Consulted by Lorisleiva\Actions\Decorators\ListenerDecorator::shouldQueue().
     */
    public bool $shouldQueueListener = true;

    /**
     * Execute the action as an event listener.
     *
     * Failures are logged and swallowed (the returned Result still carries the
     * outcome for programmatic dispatch) so a failing listener never breaks
     * Laravel's listener chain.
     *
     * @param  mixed  ...$arguments  The dispatched event payload (a single event
     *                               object, or the spread arguments of a
     *                               string-named event).
     */
    public function asListener(mixed ...$arguments): mixed
    {
        try {
            $result = $this->execute($this->mapEventToInputs($arguments), $arguments[0] ?? null);

            if ($result->isFail()) {
                Log::warning('Action listener soft-failed', [
                    'action' => $this->identifier(),
                    'message' => $result->message(),
                ]);
            }

            return $result;
        } catch (ValidationException $e) {
            Log::warning('Action listener validation failed', [
                'action' => $this->identifier(),
                'errors' => $e->errors(),
            ]);

            return Result::fail($e->getMessage());
        } catch (Throwable $e) {
            Log::error('Action listener failed', [
                'action' => $this->identifier(),
                'error' => $e->getMessage(),
            ]);

            return Result::fail($e->getMessage());
        }
    }

    /**
     * Decide whether the listener should be queued.
     *
     * @param  mixed  ...$arguments  The dispatched event payload (unused by the
     *                               default; available for overrides).
     */
    public function shouldQueue(mixed ...$arguments): bool
    {
        return $this->shouldQueueListener;
    }

    /**
     * Normalize the dispatched event payload and map it against the Action's
     * declared parameters(). Associative data is matched by key; leftover
     * positional values are matched against the remaining parameters in
     * declared order. Keys that are not declared parameters are discarded.
     *
     * @param  array<int|string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function mapEventToInputs(array $arguments): array
    {
        $names = array_column($this->parameters(), 'name');

        /** @var array<string, mixed> $assoc */
        $assoc = [];
        /** @var array<int, mixed> $positional */
        $positional = [];

        foreach ($arguments as $argument) {
            $normalized = $this->normalizeEventArgument($argument);

            if (array_is_list($normalized)) {
                $positional = array_merge($positional, $normalized);

                continue;
            }

            $assoc = array_merge($assoc, $normalized);
        }

        $index = 0;
        foreach ($names as $name) {
            if (array_key_exists($name, $assoc)) {
                continue;
            }

            if (! array_key_exists($index, $positional)) {
                break;
            }

            $assoc[$name] = $positional[$index];
            $index++;
        }

        return array_intersect_key($assoc, array_flip($names));
    }

    /**
     * Normalize a single event argument to an array.
     *
     * Objects are unwrapped via toArray()/Arrayable or their public properties;
     * arrays pass through; scalars become a single positional value.
     *
     * @return array<int|string, mixed>
     */
    protected function normalizeEventArgument(mixed $argument): array
    {
        if (is_array($argument)) {
            return $argument;
        }

        if ($argument instanceof Arrayable) {
            return $argument->toArray();
        }

        if (is_object($argument)) {
            if (method_exists($argument, 'toArray')) {
                return $argument->toArray();
            }

            return get_object_vars($argument);
        }

        return [$argument];
    }
}
