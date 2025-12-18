<?php

namespace Workbench\App\MCP\Servers;

use Laravel\Mcp\Server;
use Workbench\App\Services\Actions\ResetPassword;

class PlatformServer extends Server
{
    /**
     * The MCP server's name.
     */
    protected string $name = 'Platform Server';

    /**
     * The MCP server's version.
     */
    protected string $version = '1.0.0';

    /**
     * The MCP server's instructions for the LLM.
     */
    protected string $instructions = <<<'INSTRUCTIONS'
You are an assistant for the Platform administration system. You have access to user management tools.

## Available Tools

### Reset Password
Resets a user's password. Requires:
- email: The user's email address (must exist in the system)
- password: The new password (minimum 8 characters)
- password_confirmation: Must match the password

## Guidelines

1. Always confirm the user's email before resetting their password.
2. Never generate or suggest weak passwords.
3. Inform the user that the password has been changed successfully after completion.
4. If validation fails, explain what went wrong and ask for corrected input.
INSTRUCTIONS;

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string>
     */
    protected array $tools = [
        ResetPassword::class,
    ];
}
