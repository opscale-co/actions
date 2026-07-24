<?php

declare(strict_types=1);

namespace Opscale\Actions\Adapters;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsCommand;
use Throwable;

/**
 * Trait CommandAdapter
 *
 * Adapts the Action contract to Lorisleiva\Actions\Concerns\AsCommand via
 * the SIPOC pipeline.
 *
 * - identifier() → command signature root
 * - name()       → getCommandName()
 * - description()→ getCommandDescription()
 * - parameters() → CLI arguments + interactive prompts
 * - options()    → renders `choice()` prompts instead of plain text
 * - prefill()    → resolved silently (never prompted; closures receive the
 *                  Command instance as context)
 *
 * @see AsCommand
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
     * Build the command signature: identifier followed by an optional
     * argument for every user-supplied parameter.
     */
    public function getCommandSignature(): string
    {
        $signature = $this->identifier();
        $prefill = $this->prefill();

        foreach ($this->parameters() as $parameter) {
            $name = $parameter['name'];

            if (array_key_exists($name, $prefill)) {
                continue;
            }

            $signature .= " {{$name}?}";
        }

        return $signature;
    }

    public function getCommandName(): string
    {
        return $this->name();
    }

    public function getCommandDescription(): string
    {
        return $this->description();
    }

    public function getCommandHelp(): string
    {
        if ($this->commandHelp !== null) {
            return $this->commandHelp;
        }

        $parameters = $this->parameters();
        if (! empty($parameters)) {
            $help = "Parameters:\n";
            foreach ($parameters as $param) {
                $required = $this->isParameterRequired($param) ? '(required)' : '(optional)';
                $help .= sprintf(
                    "  %s: %s %s\n",
                    $param['name'],
                    $param['description'] ?? '',
                    $required,
                );
            }

            return $help;
        }

        return '';
    }

    public function isCommandHidden(): bool
    {
        return $this->commandHidden;
    }

    /**
     * Execute the action as an Artisan command.
     */
    public function asCommand(Command $command): int
    {
        try {
            $result = $this->execute(
                $this->collectArguments($command),
                $command,
            );

            if ($result->isFail()) {
                $command->error($result->message() ?? 'This action cannot be executed.');

                return Command::FAILURE;
            }

            $message = $result->data()['message'] ?? 'Done.';
            $command->info($message);

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
     * Collect user-supplied parameters from CLI args or interactively.
     *
     * @return array<string, mixed>
     */
    protected function collectArguments(Command $command): array
    {
        $prefill = $this->prefill();
        $options = $this->options();
        $collected = [];

        foreach ($this->parameters() as $parameter) {
            $name = $parameter['name'];

            if (array_key_exists($name, $prefill)) {
                continue;
            }

            $value = $command->hasArgument($name) ? $command->argument($name) : null;

            if ($value === null) {
                $value = $this->promptForArgument($command, $parameter, $options);
            }

            $collected[$name] = $this->castParameterValue($value, $parameter);
        }

        return $collected;
    }

    /**
     * Prompt the user for a parameter value.
     *
     * @param  array<string, array<int|string, string>>  $options
     */
    protected function promptForArgument(Command $command, array $parameter, array $options = []): mixed
    {
        $name = $parameter['name'];
        $description = $parameter['description'] ?? Str::headline($name);
        $type = $parameter['type'] ?? 'string';
        $isRequired = $this->isParameterRequired($parameter);

        if (array_key_exists($name, $options) && ! empty($options[$name])) {
            return $this->promptChoice($command, $description, $options[$name], $isRequired);
        }

        if ($type === 'boolean' || $type === 'bool') {
            return $command->confirm($description, $parameter['default'] ?? false);
        }

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
     *
     * @param  array<int|string, string>  $choices
     */
    protected function promptChoice(Command $command, string $description, array $choices, bool $required): mixed
    {
        if ($required) {
            return $command->choice($description, $choices);
        }

        $choicesWithEmpty = array_merge(['(none)'], $choices);
        $value = $command->choice($description, $choicesWithEmpty);

        return $value === '(none)' ? null : $value;
    }

    protected function isParameterRequired(array $parameter): bool
    {
        $rules = $parameter['rules'] ?? [];

        return in_array('required', $rules, true);
    }

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
