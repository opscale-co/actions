<?php

namespace Opscale\Actions\Adapters;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Trait CommandAdapter
 *
 * Adapts the Action contract to Lorisleiva\Actions\Concerns\AsCommand.
 * This trait maps the Action's abstract methods to the command properties:
 *
 * - identifier() → getCommandSignature()
 * - name() → getCommandName()
 * - description() → getCommandDescription()
 * - parameters() → interactive prompts via asCommand()
 *
 * @see \Lorisleiva\Actions\Concerns\AsCommand
 */
trait CommandAdapter
{
    /**
     * Whether the command should be hidden from the command list.
     */
    public bool $commandHidden = false;

    /**
     * Additional help text for the command.
     */
    public ?string $commandHelp = null;

    /**
     * Get the command signature.
     *
     * Uses identifier() as the command name and appends optional arguments
     * for each parameter defined in parameters().
     */
    public function getCommandSignature(): string
    {
        $signature = $this->identifier();

        // Append optional arguments for each parameter
        // Parameters will be collected interactively if not provided
        foreach ($this->parameters() as $parameter) {
            $name = $parameter['name'];
            $signature .= " {{$name}?}";
        }

        return $signature;
    }

    /**
     * Get the command name (displayed in help).
     */
    public function getCommandName(): string
    {
        return $this->name();
    }

    /**
     * Get the command description.
     */
    public function getCommandDescription(): string
    {
        return $this->description();
    }

    /**
     * Get additional help text for the command.
     */
    public function getCommandHelp(): string
    {
        if ($this->commandHelp !== null) {
            return $this->commandHelp;
        }

        // Build help text from parameters
        $parameters = $this->parameters();
        if (! empty($parameters)) {
            $help = "Parameters:\n";
            foreach ($parameters as $param) {
                $required = $this->isParameterRequired($param) ? '(required)' : '(optional)';
                $help .= sprintf(
                    "  %s: %s %s\n",
                    $param['name'],
                    $param['description'] ?? '',
                    $required
                );
            }

            return $help;
        }

        return '';
    }

    /**
     * Determine if the command should be hidden.
     */
    public function isCommandHidden(): bool
    {
        return $this->commandHidden;
    }

    /**
     * Execute the action as an Artisan command.
     *
     * Collects missing arguments interactively, executes the action,
     * and handles exceptions gracefully.
     */
    public function asCommand(Command $command): int
    {
        try {
            $parameters = $this->collectArguments($command);

            $this->fill($parameters);
            $validatedData = $this->validateAttributes();

            $result = $this->handle($validatedData);

            $command->info('Done.');

            return Command::SUCCESS;
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $command->error("{$field}: {$message}");
                }
            }

            return Command::FAILURE;
        } catch (Throwable $e) {
            $command->error($e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Collect parameters from command arguments or interactively.
     *
     * @return array<string, mixed>
     */
    protected function collectArguments(Command $command): array
    {
        $parameters = $this->parameters();
        $collected = [];

        foreach ($parameters as $parameter) {
            $name = $parameter['name'];
            $value = $command->argument($name);

            // If no value provided as argument, prompt interactively
            if ($value === null) {
                $value = $this->promptForArgument($command, $parameter);
            }

            // Cast the value to the appropriate type
            $collected[$name] = $this->castParameterValue($value, $parameter);
        }

        return $collected;
    }

    /**
     * Prompt the user for a parameter value.
     */
    protected function promptForArgument(Command $command, array $parameter): mixed
    {
        $name = $parameter['name'];
        $description = $parameter['description'] ?? Str::headline($name);
        $type = $parameter['type'] ?? 'string';
        $rules = $parameter['rules'] ?? [];
        $isRequired = $this->isParameterRequired($parameter);

        // Check for 'in:' rule to offer choices
        $choices = $this->extractChoicesFromRules($rules);

        if (! empty($choices)) {
            return $this->promptChoice($command, $description, $choices, $isRequired);
        }

        // Handle boolean type
        if ($type === 'boolean' || $type === 'bool') {
            return $command->confirm($description, $parameter['default'] ?? false);
        }

        // Handle array type
        if ($type === 'array') {
            return $this->promptArray($command, $description, $isRequired);
        }

        // Default: ask for text input
        return $this->promptText($command, $name, $description, $isRequired, $parameter['default'] ?? null);
    }

    /**
     * Prompt for a text value with validation.
     */
    protected function promptText(Command $command, string $name, string $description, bool $required, mixed $default = null): mixed
    {
        $prompt = $description;
        if ($default !== null) {
            $prompt .= " [{$default}]";
        }

        $value = $command->ask($prompt, $default);

        if ($required && ($value === null || $value === '')) {
            $command->error("The {$name} field is required.");

            return $this->promptText($command, $name, $description, $required, $default);
        }

        return $value;
    }

    /**
     * Prompt for a choice value.
     */
    protected function promptChoice(Command $command, string $description, array $choices, bool $required): mixed
    {
        if ($required) {
            return $command->choice($description, $choices);
        }

        // Add an empty option for optional fields
        $choicesWithEmpty = array_merge(['(none)'], $choices);
        $value = $command->choice($description, $choicesWithEmpty);

        return $value === '(none)' ? null : $value;
    }

    /**
     * Prompt for array values.
     */
    protected function promptArray(Command $command, string $description, bool $required): array
    {
        $command->info($description . ' (enter values one per line, empty line to finish)');
        $values = [];

        while (true) {
            $value = $command->ask('Value (or empty to finish)');
            if ($value === null || $value === '') {
                break;
            }
            $values[] = $value;
        }

        if ($required && empty($values)) {
            $command->error('At least one value is required.');

            return $this->promptArray($command, $description, $required);
        }

        return $values;
    }

    /**
     * Extract choices from validation rules (e.g., 'in:a,b,c').
     */
    protected function extractChoicesFromRules(array $rules): array
    {
        foreach ($rules as $rule) {
            if (is_string($rule) && str_starts_with($rule, 'in:')) {
                return explode(',', substr($rule, 3));
            }
        }

        return [];
    }

    /**
     * Determine if a parameter is required based on its rules.
     */
    protected function isParameterRequired(array $parameter): bool
    {
        $rules = $parameter['rules'] ?? [];

        return in_array('required', $rules, true);
    }

    /**
     * Cast parameter value to the appropriate type.
     */
    protected function castParameterValue(mixed $value, array $parameter): mixed
    {
        if ($value === null) {
            return null;
        }

        $type = $parameter['type'] ?? 'string';

        return match ($type) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'bool', 'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'array' => is_array($value) ? $value : [$value],
            default => $value,
        };
    }
}
