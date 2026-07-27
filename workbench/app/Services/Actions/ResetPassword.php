<?php

declare(strict_types=1);

namespace Workbench\App\Services\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Opscale\Actions\Action;
use Opscale\Actions\Concerns\EmitsEvent;
use Workbench\App\Models\User;

/**
 * Canonical SIPOC example.
 *
 *   Suppliers  : `user` is supplied by the system (prefill Closure that
 *                reads the selected Nova model — or falls back to the
 *                caller for API/CLI/MCP). `password` is user-supplied.
 *   Inputs     : declared in parameters().
 *   Process    : handle() hashes the password and saves it. Soft-fails if
 *                the target user is missing (via $this->fail()).
 *   Outputs    : declared in outputs().
 *   Clients    : system — `use EmitsEvent` triggers
 *                `reset-password` after every success.
 */
class ResetPassword extends Action
{
    use EmitsEvent;

    public function identifier(): string
    {
        return 'reset-password';
    }

    public function name(): string
    {
        return 'Reset Password';
    }

    public function description(): string
    {
        return 'Resets a user\'s password.';
    }

    public function parameters(): array
    {
        return [
            [
                'name' => 'user',
                'description' => 'The user whose password will be reset.',
                'type' => User::class,
                'rules' => ['required'],
            ],
            [
                'name' => 'password',
                'description' => 'The new password.',
                'type' => 'string',
                'rules' => ['required', 'string', 'min:8'],
            ],
        ];
    }

    /**
     * `user` is supplied by the system when the action runs inside Nova
     * against a selected user. In every other adapter (API/CLI/MCP) there
     * is no selected model, so the closure returns null and the caller
     * must provide the user as a normal input — the pipeline drops null
     * prefill returns so the caller value survives the merge.
     */
    public function prefill(): array
    {
        return [
            'user' => fn ($ctx) => $ctx?->models?->first(),
        ];
    }

    /**
     * Provide the list of selectable users so API/CLI/MCP callers get a
     * choice-driven UI instead of having to know an id ahead of time.
     * Nova ignores this for `user` because the parameter is prefilled
     * from the selected model, but for the other adapters it renders a
     * `Select` / `enum` / `choice()` with the user's name as label.
     *
     * @return array<string, array<int|string, string>>
     */
    public function options(): array
    {
        return [
            'user' => User::query()
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all(),
        ];
    }

    public function outputs(): array
    {
        return [
            [
                'name' => 'user_id',
                'description' => 'ID of the user whose password was reset.',
                'type' => 'integer',
                'rules' => ['required', 'integer'],
            ],
            [
                'name' => 'message',
                'description' => 'Human-readable summary.',
                'type' => 'string',
                'rules' => ['required', 'string'],
            ],
        ];
    }

    /**
     * Business rule: an operator cannot reset their own password through
     * this admin action — they should use the regular "forgot password"
     * flow instead. Returning a string produces a soft-fail with that
     * exact reason surfaced to the caller (Nova danger / API 422 /
     * MCP error / CLI stderr).
     */
    public function canRun(array $inputs = []): bool|string
    {
        $user = $inputs['user'] ?? null;
        $userId = $user instanceof User ? $user->getKey() : $user;

        if ($userId !== null && Auth::id() !== null && (int) Auth::id() === (int) $userId) {
            return 'You cannot reset your own password from this action.';
        }

        return true;
    }

    public function handle(array $inputs = []): array
    {
        $user = $inputs['user'];

        if (! $user instanceof User) {
            $user = User::find($user);
        }

        if ($user === null) {
            return $this->fail('User not found.');
        }

        $user->update(['password' => Hash::make($inputs['password'])]);

        return $this->succeed([
            'user_id' => $user->getKey(),
            'message' => 'Password reset successfully.',
        ]);
    }
}
