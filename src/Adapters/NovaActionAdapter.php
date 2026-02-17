<?php

namespace Opscale\Actions\Adapters;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\Color;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Email;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Fields\Image;
use Laravel\Nova\Fields\KeyValue;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Password;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\URL;
use Throwable;

/**
 * Trait NovaActionAdapter
 *
 * Adapts the Action contract to Nova Action.
 * This trait maps the Action's abstract methods to the Nova action properties:
 *
 * - name() → getActionTitle()
 * - identifier() → getActionUriKey()
 * - parameters() → getActionFields()
 *
 * @see \Opscale\Actions\Decorators\NovaActionDecorator
 */
trait NovaActionAdapter
{
    /**
     * Get the action title (human-readable name).
     */
    public function getActionTitle(): string
    {
        return $this->name();
    }

    /**
     * Get the action URI key (identifier).
     */
    public function getActionUriKey(): string
    {
        return $this->identifier();
    }

    /**
     * Get the action fields built from parameters().
     */
    public function getActionFields(): array
    {
        $fields = [];

        foreach ($this->parameters() as $parameter) {
            $name = $parameter['name'];
            $description = $parameter['description'] ?? '';
            $type = $parameter['type'] ?? 'string';
            $rules = $parameter['rules'] ?? [];

            // Create the appropriate Nova field based on type
            $field = $this->createNovaField($name, $type, $rules);

            // Add help text from description
            if ($description) {
                $field = $field->help($description);
            }

            // Apply validation rules
            if (! empty($rules)) {
                $field = $field->rules($rules);
            }

            // Mark as required if applicable
            if (in_array('required', $rules, true)) {
                $field = $field->required();
            }

            // Mark as nullable if applicable
            if (in_array('nullable', $rules, true)) {
                $field = $field->nullable();
            }

            $fields[] = $field;
        }

        return $fields;
    }

    /**
     * Execute the action as a Nova action.
     */
    public function asNovaAction(ActionFields $fields, Collection $models): mixed
    {
        try {
            $attributes = $fields->toArray();

            $this->fill($attributes);
            $validatedData = $this->validateAttributes();

            $result = $this->handle($validatedData);

            if (empty($result)) {
                return Action::danger('Something went wrong while executing the action.');
            }

            $message = $result['message'] ?? 'Action completed successfully.';

            return Action::message($message);
        } catch (ValidationException $e) {
            $errors = [];
            foreach ($e->errors() as $field => $messages) {
                $errors[] = "{$field}: " . implode(', ', $messages);
            }

            return Action::danger(implode("\n", $errors));
        } catch (Throwable $e) {
            return Action::danger($e->getMessage());
        }
    }

    /**
     * Create a Nova field based on the parameter type and rules.
     */
    protected function createNovaField(string $name, string $type, array $rules): mixed
    {
        $field = null;
        $label = str_replace('_', ' ', ucfirst($name));
        $prefill = $this->prefill();

        if ($type === 'array' || class_exists($type)) {
            $options = $prefill[$name];

            $field = Select::make($label, $name)
                ->options($options)
                ->displayUsingLabels();
        } elseif ($field = $this->fieldFromRule($label, $name, $rules)) {
            $field = $this->fieldFromType($label, $name, $type, $rules);
        } else {
            $field = Text::make($label, $name);
        }

        $field->default($prefill[$name] ?? null);
        $field->rules($rules);

        return $field;
    }

    /**
     * Create a Nova field based on the parameter type.
     */
    protected function fieldFromType(string $label, string $name, string $type, array $rules): mixed
    {
        $maxLength = $this->extractMaxLengthFromRules($rules);

        return match ($type) {
            'int', 'integer' => Number::make($label, $name),
            'float', 'double', 'number' => Number::make($label, $name)->step(0.01),
            'bool', 'boolean' => Boolean::make($label, $name),
            'text', 'markdown' => Textarea::make($label, $name),
            'date' => Date::make($label, $name),
            'datetime' => DateTime::make($label, $name),
            'file' => File::make($label, $name),
            'image' => Image::make($label, $name),
            'password' => Password::make($label, $name),
            'url' => URL::make($label, $name),
            'color' => Color::make($label, $name),
            'json', 'code' => Code::make($label, $name)->json(),
            'array' => Select::make($label, $name),
            'keyvalue' => KeyValue::make($label, $name),
            default => $maxLength !== null && $maxLength > 255
                ? Textarea::make($label, $name)
                : Text::make($label, $name),
        };
    }

    /**
     * Create a Nova field based on validation rules.
     *
     * @return mixed|null
     */
    protected function fieldFromRule(string $label, string $name, array $rules): mixed
    {
        foreach ($rules as $rule) {
            $ruleName = is_string($rule) ? explode(':', $rule)[0] : null;

            if ($ruleName === null) {
                continue;
            }

            $field = match ($ruleName) {
                // File-related rules
                'file', 'mimes', 'mimetypes' => File::make($label, $name),
                'image', 'dimensions' => Image::make($label, $name),

                // Date/time rules
                'date', 'date_format', 'before', 'after', 'before_or_equal', 'after_or_equal' => Date::make($label, $name),

                // String format rules
                'email' => Email::make($label, $name),
                'url', 'active_url' => URL::make($label, $name),
                'ip', 'ipv4', 'ipv6' => Text::make($label, $name),
                'mac_address' => Text::make($label, $name),
                'uuid', 'ulid' => Text::make($label, $name),

                // Password rules
                'password', 'current_password' => Password::make($label, $name),

                // JSON/array rules
                'json' => Code::make($label, $name)->json(),
                'array' => KeyValue::make($label, $name),

                // Numeric rules (already handled by type, but rules can override)
                'numeric', 'integer', 'digits', 'digits_between' => Number::make($label, $name),
                'decimal' => Number::make($label, $name)->step(0.01),

                // Boolean rules
                'boolean', 'accepted', 'accepted_if', 'declined', 'declined_if' => Boolean::make($label, $name),

                default => null,
            };

            if ($field !== null) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Extract max length from validation rules.
     */
    protected function extractMaxLengthFromRules(array $rules): ?int
    {
        foreach ($rules as $rule) {
            if (is_string($rule) && str_starts_with($rule, 'max:')) {
                return (int) substr($rule, 4);
            }
        }

        return null;
    }
}
