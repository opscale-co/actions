<?php

namespace Workbench\App\Services\Actions;

use Illuminate\Support\Facades\Hash;
use Opscale\Actions\Action;
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
            [
                'name' => 'password_confirmation',
                'description' => 'Confirm the new password',
                'type' => 'string',
                'rules' => ['required', 'string', 'same:password'],
            ],
        ];
    }

    public function handle(array $attributes = []): array
    {
        $this->fill($attributes);
        $validatedData = $this->validateAttributes();

        $user = User::where('email', $validatedData['email'])->firstOrFail();

        $user->update([
            'password' => Hash::make($validatedData['password']),
        ]);

        return [
            'success' => true,
            'message' => 'Password reset successfully',
        ];
    }
}
