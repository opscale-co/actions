<?php

declare(strict_types=1);

namespace Workbench\App\Services\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Nova\Actions\Action as NovaAction;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Password;
use Laravel\Nova\Fields\PasswordConfirmation;
use Opscale\Actions\Action;
use Throwable;
use Workbench\App\Models\User;

class ResetPassword extends Action
{
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
        return 'Resets a user\'s password';
    }

    public function parameters(): array
    {
        return [
            [
                'name' => 'email',
                'description' => 'The email address of the user',
                'type' => 'string',
                'rules' => ['required', 'email', 'exists:users,email'],
            ],
            [
                'name' => 'password',
                'description' => 'The new password',
                'type' => 'string',
                'rules' => ['required', 'string', 'min:8'],
            ],
        ];
    }

    /**
     * Skip the email field on the Nova form — it's derived from the selected
     * user, the operator only types the new password.
     */
    public function getActionFields(): array
    {
        return [
            Password::make('Password', 'password')
                ->rules('required', 'string', 'min:8')
                ->required(),
            PasswordConfirmation::make('Password Confirmation', 'password_confirmation')
                ->rules('required', 'string', 'same:password')
                ->required(),
        ];
    }

    /**
     * Override the Nova execution so the email is taken from the selected
     * user instead of being submitted by the operator.
     *
     * Nova's validateFields() already ran our getActionFields() rules
     * (required + same:password), so we update the user directly here
     * without re-running parameters()-based validation — that pipeline
     * would re-confirm against attributes mutated by the validator and
     * spuriously fail the same:password rule.
     */
    public function asNovaAction(ActionFields $fields, Collection $models): mixed
    {
        try {
            $attributes = array_merge($fields->toArray(), $this->prefill());
            $attributes['email'] = $models->first()->email;

            $this->fill($attributes);
            $validatedData = $this->validateAttributes();

            $label = $this->resolveParameterLabel($models);
            $validatedData[$label] = $models;

            $result = $this->handle($validatedData);

            if (empty($result)) {
                return NovaAction::danger('Something went wrong while executing the action.');
            }

            $message = $result['message'] ?? 'Action completed successfully.';

            return NovaAction::message($message);
        } catch (ValidationException $e) {
            $errors = [];
            foreach ($e->errors() as $field => $messages) {
                $errors[] = "{$field}: ".implode(', ', $messages);
            }

            return NovaAction::danger(implode("\n", $errors));
        } catch (Throwable $e) {
            return NovaAction::danger($e->getMessage());
        }
    }

    public function handle(array $attributes = []): array
    {
        $user = User::where('email', $attributes['email'])->firstOrFail();

        $user->update([
            'password' => Hash::make($attributes['password']),
        ]);

        return [
            'success' => true,
            'message' => 'Password reset successfully',
        ];
    }
}
