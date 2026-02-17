<?php

namespace Opscale\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Opscale\Actions\Adapters\CommandAdapter;
use Opscale\Actions\Adapters\ControllerAdapter;
use Opscale\Actions\Adapters\MCPToolAdapter;
use Opscale\Actions\Adapters\NovaActionAdapter;
use Opscale\Actions\Concerns\AsMCPTool;
use Opscale\Actions\Concerns\AsNovaAction;
use Opscale\Actions\Concerns\SerializesModels;

/**
 * Abstract base class for all Opscale actions.
 *
 * This class provides a unified interface for defining actions that can be used
 * across multiple contexts: controllers, jobs, commands, Nova actions, and MCP tools.
 *
 * By extending this class, you get:
 * - Automatic access to AsAction and WithAttributes functionality
 * - Consistent identification and naming across all contexts
 * - Built-in validation support
 * - Type safety and IDE autocomplete
 *
 * Example:
 *
 * ```php
 * class UpdateUserStatus extends Action
 * {
 *     use AsNovaAction;
 *     use AsMCPTool;
 *
 *     public function identifier(): string
 *     {
 *         return 'update-user-status';
 *     }
 *
 *     public function name(): string
 *     {
 *         return 'Update User Status';
 *     }
 *
 *     public function description(): string
 *     {
 *         return 'Updates the status of one or more users';
 *     }
 *
 *     public function parameters(): array
 *     {
 *         return [
 *             [
 *                 'name' => 'status',
 *                 'description' => 'The new status for the user',
 *                 'type' => 'string',
 *                 'rules' => ['required', 'string', 'in:active,inactive,pending'],
 *             ],
 *             [
 *                 'name' => 'reason',
 *                 'description' => 'Optional reason for the status change',
 *                 'type' => 'string',
 *                 'rules' => ['nullable', 'string', 'max:500'],
 *             ],
 *         ];
 *     }
 *
 *     public function handle(array $attributes = []): array
 *     {
 *         $status = $attributes['status'];
 *         $reason = $attributes['reason'] ?? null;
 *
 *         // Your business logic here
 *
 *         return ['success' => true];
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
     * Get a unique slug identifier for this action.
     *
     * This identifier is used across different contexts to uniquely identify
     * the action. It should be a slug string (lowercase, hyphenated).
     *
     * Examples:
     * - 'update-user-status'
     * - 'send-invoice-email'
     * - 'generate-monthly-report'
     *
     * This is used by:
     * - Nova actions: as the URI key
     * - MCP tools: as the tool name
     * - Logs and auditing: for tracking action execution
     *
     * @return string A slug identifier (lowercase, hyphenated)
     */
    abstract public function identifier(): string;

    /**
     * Get the human-readable name of the action.
     *
     * This name is displayed to users in various contexts and should be
     * descriptive and clear about what the action does.
     *
     * Examples:
     * - 'Update User Status'
     * - 'Send Invoice Email'
     * - 'Generate Monthly Report'
     *
     * This is used by:
     * - Nova actions: as the action name in the UI
     * - MCP tools: as the tool title
     * - Logs: for human-readable action identification
     *
     * @return string A human-readable action name
     */
    abstract public function name(): string;

    /**
     * Get a detailed description of what this action does.
     *
     * This description should explain the business logic and purpose of the action.
     * It's shown to users to help them understand what will happen when they
     * execute the action.
     *
     * Examples:
     * - 'Updates the status of one or more users. You can optionally provide a reason for the status change.'
     * - 'Sends an invoice email to the customer with a PDF attachment.'
     * - 'Generates a monthly report summarizing all sales activities and exports it to Excel.'
     *
     * This is used by:
     * - Nova actions: as help text or confirmation message
     * - MCP tools: as the tool description
     * - Documentation: for generating action documentation
     *
     * @return string A detailed description of the action
     */
    abstract public function description(): string;

    /**
     * Define the parameters schema for this action.
     *
     * This method returns an array of parameter definitions, where each parameter
     * includes its name, description, type, and validation rules. This schema is
     * used across different contexts to generate forms, validate inputs, and
     * provide documentation.
     *
     * Each parameter should be defined as an array with:
     * - name: The parameter name (string)
     * - description: A human-readable description of what this parameter does (string)
     * - type: The data type (string, integer, boolean, array, etc.)
     * - rules: Laravel validation rules (array)
     *
     * Example:
     *
     * ```php
     * public function parameters(): array
     * {
     *     return [
     *         [
     *             'name' => 'email',
     *             'description' => 'The email address of the user',
     *             'type' => 'string',
     *             'rules' => ['required', 'email', 'exists:users,email'],
     *         ],
     *         [
     *             'name' => 'status',
     *             'description' => 'The new status for the user',
     *             'type' => 'string',
     *             'rules' => ['required', 'string', 'in:active,inactive'],
     *         ],
     *         [
     *             'name' => 'priority',
     *             'description' => 'Priority level for this operation',
     *             'type' => 'integer',
     *             'rules' => ['nullable', 'integer', 'between:1,10'],
     *         ],
     *     ];
     * }
     * ```
     *
     * This is used by:
     * - Nova actions: to generate form fields and validation
     * - MCP tools: to define tool parameters and validation
     * - Controllers: for request validation
     * - Documentation: for auto-generating API documentation
     *
     * @return array<int, array{name: string, description: string, type: string, rules: array}> Array of parameter schemas
     */
    abstract public function parameters(): array;

    /**
     * Execute the action with the given attributes.
     *
     * This is the main entry point for action execution. It receives validated
     * attributes and should return an array with the result of the action.
     *
     * @param  array  $attributes  The validated attributes for this action
     * @return array The result of the action execution
     */
    abstract public function handle(array $attributes = []): array;

    /**
     * Get prefill data for each parameter, including default values and options.
     *
     * @return array<string, array{default: mixed, options: array}>
     */
    public function prefill(): array {}

    /**
     * Get the validation rules for this action.
     *
     * This method converts the parameters schema into Laravel validation rules
     * format (attribute => rules). It's used by WithAttributes for validation.
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
}
