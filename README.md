## Support us

At Opscale, we’re passionate about contributing to the open-source community by providing solutions that help businesses scale efficiently. If you’ve found our tools helpful, here are a few ways you can show your support:

⭐ **Star this repository** to help others discover our work and be part of our growing community. Every star makes a difference!

💬 **Share your experience** by leaving a review on [Trustpilot](https://www.trustpilot.com/review/opscale.co) or sharing your thoughts on social media. Your feedback helps us improve and grow!

📧 **Send us feedback** on what we can improve at [feedback@opscale.co](mailto:feedback@opscale.co). We value your input to make our tools even better for everyone.

🙏 **Get involved** by actively contributing to our open-source repositories. Your participation benefits the entire community and helps push the boundaries of what’s possible.

💼 **Hire us** if you need custom dashboards, admin panels, internal tools or MVPs tailored to your business. With our expertise, we can help you systematize operations or enhance your existing product. Contact us at hire@opscale.co to discuss your project needs.

Thanks for helping Opscale continue to scale! 🚀

## Description

> **One logic unit to rule them all.**

Encapsulate your business logic in atomic, self-contained classes and reuse them across your entire application stack. Write once, use everywhere.

This package extends [Laravel Actions](https://laravelactions.com) with support for:
- **Laravel Nova Actions** - Use actions directly as Nova actions
- **Laravel MCP Tools** - Use actions as Model Context Protocol tools for AI

The same logic can serve multiple audiences:
- **For end users** → Nova Actions in your admin panel
- **For administrators** → Artisan commands in the terminal
- **For external systems** → API endpoints via controllers
- **For AI agents** → MCP tools for intelligent automation

![Actions demo](https://raw.githubusercontent.com/opscale-co/actions/refs/heads/main/screenshots/actions.gif)

## Installation

[![Latest Version on Packagist](https://img.shields.io/packagist/v/opscale-co/actions.svg?style=flat-square)](https://packagist.org/packages/opscale-co/actions)

You can install the package via composer:

```bash
composer require opscale-co/actions
```

The package is automatically registered via Laravel's package discovery.

## Usage

### Real-World Example: ResetPassword

Here's a complete example showing how a single `ResetPassword` action can serve your entire application:

```php
use Opscale\Actions\Action;

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
        $validated = $this->validateAttributes();

        $user = User::where('email', $validated['email'])->firstOrFail();
        $user->update(['password' => Hash::make($validated['password'])]);

        return [
            'success' => true, 
            'message' => 'Password reset successfully'
        ];
    }

    public function canRun(): bool
    {
        return auth()->user()?->can('reset-passwords') ?? false;
    }
}
```

### Authorization

Every action exposes a `canRun(): bool` method that defaults to `true`. Override it to gate execution based on the current user, feature flags, or any custom rule. The Nova decorator wires this into Nova's `canSee`, so unauthorized actions are automatically hidden from the toolbar.

### Context, Prefill & Options

Two optional hooks let you trim what each adapter solicits from the user and what the action takes as authoritative:

| Hook | Role | Shown to user |
|---|---|---|
| `prefill()` | Authoritative defaults for parameters. Wins over user input. Hidden in every adapter. | Hidden |
| `options()` | Choice list per parameter for the values the user IS asked for. Nova renders a Select, Artisan renders a choice prompt, MCP exposes an `enum`. | Visible |

A single `context()` reader exposes whatever the active adapter populated — request, user, models, command, etc. Each adapter knows what to inject for its surface, so `prefill()` reads the same shape regardless of where the action is invoked.

```php
class ResetPassword extends Action
{
    public function parameters(): array
    {
        return [
            ['name' => 'email',    'description' => 'Target email',   'type' => 'string', 'rules' => ['required', 'email']],
            ['name' => 'role',     'description' => 'Role to assign', 'type' => 'string', 'rules' => ['required', 'string']],
            ['name' => 'actor_id', 'description' => 'Who triggered',  'type' => 'integer','rules' => ['required', 'integer']],
        ];
    }

    public function prefill(): array
    {
        // actor_id is never asked — taken from the current request, whichever
        // adapter is driving the call.
        return [
            'actor_id' => $this->context()['user']?->id,
        ];
    }

    public function options(): array
    {
        // role IS asked, but constrained to these values.
        return ['role' => ['admin', 'editor', 'viewer']];
    }

    public function handle(array $attributes = []): array
    {
        // $attributes['actor_id'] is always the authenticated user id.
        // $attributes['role'] is one of admin/editor/viewer.
        // …
    }
}
```

Prefilled values always win over user-supplied values (an API client sending `actor_id => 999` will still see `actor_id` set to the authenticated user's id).

### One Action, Multiple Contexts

Now this single class can be used everywhere:

```php
// For end users → Nova Action in admin panel
// Register in your Nova Resource:
public function actions(NovaRequest $request)
{
    return [
        ResetPassword::make()
    ];
}

// For administrators → Artisan command
// php artisan reset-password --email=user@example.com --password=newpass123
$this->commands([
    ResetPassword::class,
]);

// For external systems → API endpoint
Route::post('/api/reset-password', ResetPassword::class);

// For AI agents → MCP tool
// Register in your MCP Server:
class PlatformServer extends Server
{
    protected array $tools = [
        ResetPassword::class
    ];
}
```

### Opinionated Design

This package is an opinionated implementation that enforces the use of `WithAttributes` from [Laravel Actions](https://laravelactions.com). All input data flows through `fill()` and `validateAttributes()`, ensuring consistent parameters validation and attribute handling across all contexts.

The package provides a default behavior for all four audiences (Nova Actions, Artisan Commands, Controllers, and MCP Tools), so your actions work out of the box without additional configuration, but it can be overriden using the speficic methods for each output.

## Testing

``` bash

npm run test

```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](https://github.com/opscale-co/.github/blob/main/CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email development@opscale.co instead of using the issue tracker.

## Credits

Built by [Opscale](https://github.com/opscale-co) on top of:
- [Laravel Actions](https://laravelactions.com) by Loris Leiva
- [Laravel Nova](https://nova.laravel.com) by Laravel
- [Laravel MCP](https://github.com/laravel/mcp) by Laravel

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.