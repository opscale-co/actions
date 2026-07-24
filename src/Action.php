<?php

declare(strict_types=1);

namespace Opscale\Actions;

use Closure;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Opscale\Actions\Adapters\CommandAdapter;
use Opscale\Actions\Adapters\ControllerAdapter;
use Opscale\Actions\Adapters\MCPToolAdapter;
use Opscale\Actions\Adapters\NovaActionAdapter;
use Opscale\Actions\Concerns\AsMCPTool;
use Opscale\Actions\Concerns\AsNovaAction;
use Opscale\Actions\Concerns\EmitsEvent;
use Opscale\Actions\Concerns\SerializesModels;
use Opscale\Actions\Results\Result;

/**
 * Abstract base class for all Opscale actions — SIPOC-aligned.
 *
 * Every action declares its Suppliers, Inputs, Process, Outputs and Clients
 * so it can run consistently across Nova, API, CLI and MCP without the
 * concrete class caring which context it's in.
 *
 *   S — Supplier : implicit. Parameters listed in `prefill()` are supplied
 *                  by the system (auth, request, cache, adapter context);
 *                  everything else is supplied by the user (form field,
 *                  CLI arg, MCP arg, request body).
 *   I — Inputs   : `parameters()` — canonical schema. Choice lists live in
 *                  `options()` (kept separate from prefill to avoid mixing
 *                  "system-supplied value" with "allowed user values").
 *   P — Process  : `canRun($inputs): bool|string` gate → `handle($inputs)`.
 *                  Soft fail via `$this->fail(...)`; hard fail via exception.
 *   O — Outputs  : `outputs()` — canonical schema for what `handle()` returns.
 *   C — Client   : implicit. Actions that `use EmitsEvent;` are "system"
 *                  clients — the pipeline dispatches a string-named event
 *                  after every success. Otherwise the outputs only travel
 *                  back to the caller in the adapter's response.
 *
 * Method order below follows SIPOC:
 *   Metadata → Supplier → Inputs → Process → Outputs → helpers/pipeline.
 *
 * Example:
 *
 * ```php
 * class UpdateUserStatus extends Action
 * {
 *     use EmitsEvent;
 *
 *     public function identifier(): string { return 'update-user-status'; }
 *     public function name(): string       { return 'Update User Status'; }
 *     public function description(): string { return 'Updates the status of a user'; }
 *
 *     public function prefill(): array
 *     {
 *         return ['actor_id' => fn () => auth()->id()];
 *     }
 *
 *     public function parameters(): array
 *     {
 *         return [
 *             ['name' => 'user_id', 'type' => 'integer', 'rules' => ['required', 'integer']],
 *             ['name' => 'status',  'type' => 'string',  'rules' => ['required', 'in:active,inactive,pending']],
 *             ['name' => 'actor_id', 'type' => 'integer', 'rules' => ['required', 'integer']],
 *         ];
 *     }
 *
 *     public function options(): array
 *     {
 *         return ['status' => ['active', 'inactive', 'pending']];
 *     }
 *
 *     public function canRun(array $inputs = []): bool|string
 *     {
 *         return $inputs['actor_id'] !== $inputs['user_id']
 *             ?: 'A user cannot change their own status.';
 *     }
 *
 *     public function handle(array $inputs = []): array
 *     {
 *         // ... update user ...
 *         return $this->succeed(['user_id' => $inputs['user_id'], 'status' => $inputs['status']]);
 *     }
 *
 *     public function outputs(): array
 *     {
 *         return [
 *             ['name' => 'user_id', 'type' => 'integer', 'rules' => ['required', 'integer']],
 *             ['name' => 'status',  'type' => 'string',  'rules' => ['required', 'string']],
 *         ];
 *     }
 * }
 * ```
 */
abstract class Action
{
    use AsAction;
    use AsMCPTool;
    use AsNovaAction;
    use CommandAdapter;
    use ControllerAdapter;
    use MCPToolAdapter;
    use NovaActionAdapter;
    use SerializesModels;
    use WithAttributes;

    /**
     * Reserved key used by `succeed()` / `fail()` helpers to mark the shape
     * of a handle() return without imposing a class type on the callable.
     */
    private const STATUS_KEY = '_status';

    private const STATUS_SUCCESS = 'success';

    private const STATUS_FAIL = 'fail';

    private const MESSAGE_KEY = '_message';

    // ── Metadata ────────────────────────────────────────────────────────

    /**
     * Get a unique slug identifier for this action.
     *
     * Used as Nova URI key, MCP tool name, Artisan command name and the
     * suffix of the system event (`opscale.action.{identifier}`).
     *
     * @return string A slug identifier (lowercase, hyphenated)
     */
    abstract public function identifier(): string;

    /**
     * Get the human-readable name of the action.
     */
    abstract public function name(): string;

    /**
     * Get a detailed description of what this action does.
     */
    abstract public function description(): string;

    // ── S · Suppliers ───────────────────────────────────────────────────

    /**
     * Declare parameters supplied by the system (SIPOC Supplier = System).
     *
     * Keys must be parameter names. Values may be scalars, arrays, or a
     * `Closure` invoked at runtime with the adapter's context:
     *
     *   - Nova     : the selected `Collection` of models (or null when none)
     *   - API      : the current `Illuminate\Http\Request`
     *   - CLI      : the `Illuminate\Console\Command` instance
     *   - MCP      : the current `Laravel\Mcp\Request`
     *
     * Prefilled parameters are hidden from the caller: Nova skips the field,
     * MCP omits the property from the schema, the CLI signature drops the
     * argument, and the controller ignores caller-supplied values for that
     * key. Prefill always wins over caller input.
     *
     * @return array<string, mixed>
     */
    public function prefill(): array
    {
        return [];
    }

    // ── I · Inputs ──────────────────────────────────────────────────────

    /**
     * Declare the input schema — SIPOC Inputs.
     *
     * Each entry:
     *   ['name' => string, 'description' => string, 'type' => string, 'rules' => array]
     *
     * @return array<int, array{name: string, description?: string, type?: string, rules?: array}>
     */
    abstract public function parameters(): array;

    /**
     * Declare choice lists for parameters — used to render `Select` fields
     * in Nova, `enum` in MCP JSON Schema, and `choice()` in the CLI.
     *
     * Keys are parameter names. Values can be a list of strings, or a
     * value-keyed map (`['admin' => 'Administrator']`). Validation of the
     * chosen value belongs to the `rules` (e.g. `in:active,inactive`).
     *
     * @return array<string, array<int|string, string>>
     */
    public function options(): array
    {
        return [];
    }

    // ── P · Process ─────────────────────────────────────────────────────

    /**
     * Gate the Process (SIPOC pre-P).
     *
     * Invoked after inputs are resolved and validated, before `handle()`.
     *
     *   - `true`   → the action runs.
     *   - `false`  → soft fail with a generic message.
     *   - string  → soft fail with the given reason.
     *
     * Never throws — an exception here would be treated as a hard fail.
     *
     * @param  array<string, mixed>  $inputs
     */
    public function canRun(array $inputs = []): bool|string
    {
        return true;
    }

    /**
     * Execute the action's Process (SIPOC P).
     *
     * Receives the validated inputs (user-supplied + resolved prefill) and
     * returns an array. Two documented outcomes:
     *
     *   - Success: return the outputs array, ideally wrapped with
     *     `$this->succeed([...])` to make the intent explicit.
     *   - Soft fail: return `$this->fail('reason', [...])`. The adapters
     *     translate this to their native error channel; no system event
     *     is dispatched.
     *
     * Any uncaught `Throwable` is a hard fail — the pipeline lets it bubble
     * and each adapter surfaces it appropriately.
     *
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    abstract public function handle(array $inputs = []): array;

    // ── O · Outputs ─────────────────────────────────────────────────────

    /**
     * Declare the output schema — SIPOC Outputs.
     *
     * Same shape as `parameters()`. The array returned by `handle()` is
     * validated against these rules on success; a violation re-throws as
     * `ValidationException` (a programmer bug — the schema is a contract).
     *
     * MCP publishes this as the tool's `outputSchema`.
     *
     * @return array<int, array{name: string, description?: string, type?: string, rules?: array}>
     */
    abstract public function outputs(): array;

    // ── C · Clients ─────────────────────────────────────────────────────
    // System clients: `use Opscale\Actions\Concerns\EmitsEvent;` on the
    // concrete class — the pipeline dispatches
    // `event("opscale.action.{identifier}", [$outputs])` after each success.
    // User clients: the outputs travel back to the caller through the
    // adapter's native response (Nova message, JSON body, CLI stdout,
    // MCP text). No extra declaration needed.

    // ── Helpers ─────────────────────────────────────────────────────────

    /**
     * Helper for user code: mark a handle() return as a success payload.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function succeed(array $data = []): array
    {
        return array_merge($data, [self::STATUS_KEY => self::STATUS_SUCCESS]);
    }

    /**
     * Helper for user code: mark a handle() return as a soft-fail.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function fail(string $message, array $data = []): array
    {
        return array_merge($data, [
            self::STATUS_KEY => self::STATUS_FAIL,
            self::MESSAGE_KEY => $message,
        ]);
    }

    /**
     * Get the validation rules for this action's inputs.
     *
     * Converts `parameters()` into the Laravel rules array WithAttributes
     * expects.
     *
     * @return array<string, array>
     */
    public function rules(): array
    {
        $rules = [];

        foreach ($this->parameters() as $parameter) {
            $rules[$parameter['name']] = $parameter['rules'] ?? [];
        }

        return $rules;
    }

    // ── Pipeline (internal) ─────────────────────────────────────────────

    /**
     * Run the full SIPOC pipeline and return a `Result`.
     *
     * Each adapter (Nova / API / CLI / MCP) prepares raw user-supplied inputs
     * and an optional context object (the selected models for Nova, the
     * Request for the controller, etc.), then calls `execute()`. The
     * pipeline:
     *
     *   1. Resolves `prefill()` — closures are invoked with the adapter context.
     *   2. Merges resolved prefill on top of raw user inputs (prefill wins).
     *   3. Validates the merged bag against `parameters()` rules.
     *   4. Invokes `canRun($validated)` gate. false/string → soft-fail.
     *   5. Calls `handle($validated)`. Uncaught throwables propagate (hard fail).
     *   6. Wraps the raw return into a `Result` (detects `_status` marker set by
     *      the `succeed()` / `fail()` helpers).
     *   7. On soft fail, returns the `Result` without validating outputs or
     *      dispatching events.
     *   8. Validates outputs against `outputs()` rules — a violation is a
     *      programmer bug and re-throws as a `ValidationException`.
     *   9. If the action uses `EmitsEvent`, dispatches
     *      `event("opscale.action.{identifier}", [$outputs])`.
     *  10. Returns the success `Result`.
     *
     * @param  array<string, mixed>  $rawUserInputs  Inputs supplied by the caller.
     * @param  mixed  $context  Adapter-specific context passed to prefill closures.
     * @param  array<string, mixed>  $extras  Extras merged into the validated
     *                                        input bag AFTER validation. Used
     *                                        for adapter conventions that are
     *                                        not declared in `parameters()`
     *                                        (e.g. the Nova selected-models
     *                                        collection injected as `user`
     *                                        or `users`).
     */
    protected function execute(array $rawUserInputs, mixed $context = null, array $extras = []): Result
    {
        $resolvedPrefill = $this->resolvePrefill($context);
        $merged = array_merge($rawUserInputs, $resolvedPrefill);

        $this->fill($merged);
        $validated = array_merge($this->validateAttributes(), $extras);

        $gate = $this->canRun($validated);
        if ($gate !== true) {
            $message = is_string($gate) ? $gate : 'This action cannot be executed.';

            return Result::fail($message);
        }

        $raw = $this->handle($validated);
        $result = $this->interpretHandleReturn($raw);

        if ($result->isFail()) {
            return $result;
        }

        $this->validateOutputs($result->data());

        if ($this->usesEmitsEvent()) {
            event("opscale.action.{$this->identifier()}", [$result->data()]);
        }

        return $result;
    }

    /**
     * Evaluate the closures in prefill() with the adapter context. Scalar and
     * array values pass through unchanged.
     *
     * A closure that returns null is DROPPED from the resolved map, so the
     * caller-supplied value (if any) survives the merge. This lets the same
     * Action declare a system supplier that only applies in some contexts
     * (e.g. the selected Nova model's email) while gracefully deferring to
     * the caller in others (API/CLI/MCP with no selected model).
     *
     * @return array<string, mixed>
     */
    protected function resolvePrefill(mixed $context = null): array
    {
        $resolved = [];

        foreach ($this->prefill() as $key => $value) {
            if ($value instanceof Closure) {
                $resolvedValue = $value($context);
                if ($resolvedValue === null) {
                    continue;
                }
                $resolved[$key] = $resolvedValue;

                continue;
            }

            $resolved[$key] = $value;
        }

        return $resolved;
    }

    /**
     * Detect whether the action uses the EmitsEvent trait.
     */
    protected function usesEmitsEvent(): bool
    {
        return in_array(
            EmitsEvent::class,
            $this->classUsesRecursive(static::class),
            true,
        );
    }

    /**
     * Validate the handle() return against outputs() rules. Failure here is
     * a programmer error, not a user error — re-throw as-is.
     *
     * @param  array<string, mixed>  $data
     */
    protected function validateOutputs(array $data): void
    {
        $rules = [];

        foreach ($this->outputs() as $output) {
            $rules[$output['name']] = $output['rules'] ?? [];
        }

        if ($rules === []) {
            return;
        }

        Validator::make($data, $rules)->validate();
    }

    /**
     * Inspect the return of handle() and normalize it into a Result.
     *
     * @param  array<string, mixed>  $raw
     */
    protected function interpretHandleReturn(array $raw): Result
    {
        $status = $raw[self::STATUS_KEY] ?? null;
        $message = $raw[self::MESSAGE_KEY] ?? null;
        unset($raw[self::STATUS_KEY], $raw[self::MESSAGE_KEY]);

        if ($status === self::STATUS_FAIL) {
            return Result::fail($message ?? 'This action failed.', $raw);
        }

        return Result::success($raw);
    }

    /**
     * Local copy of Laravel's class_uses_recursive helper (avoids a hard
     * dependency on the global function during test discovery).
     *
     * @return array<int, string>
     */
    private function classUsesRecursive(string $class): array
    {
        if (function_exists('class_uses_recursive')) {
            return class_uses_recursive($class);
        }

        $results = [];

        foreach (array_reverse(class_parents($class) ?: []) + [$class => $class] as $c) {
            $results += trait_uses_recursive($c);
        }

        return array_unique($results);
    }
}
