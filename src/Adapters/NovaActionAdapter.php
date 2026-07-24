<?php

declare(strict_types=1);

namespace Opscale\Actions\Adapters;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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
use Opscale\Actions\Decorators\NovaActionDecorator;
use Throwable;

/**
 * Trait NovaActionAdapter
 *
 * Adapts the Action contract to Nova Action.
 *
 * - name()       → getActionTitle()
 * - identifier() → getActionUriKey()
 * - parameters() → getActionFields() (skipping prefill()'d ones; using
 *                                     options() for choice lists)
 * - outputs()    → validated after handle(); user-facing summary shown via
 *                  NovaAction::message()
 *
 * Prefill closures receive an anonymous context object exposing `->fields`
 * (the submitted ActionFields) and `->models` (the selected model
 * Collection). The pipeline injects the models Collection into `handle()`'s
 * inputs under a snake_case key (`user`, `users`, `order_items`, ...) as a
 * Nova convenience.
 *
 * @see NovaActionDecorator
 */
trait NovaActionAdapter
{
    public function getActionTitle(): string
    {
        return $this->name();
    }

    public function getActionUriKey(): string
    {
        return $this->identifier();
    }

    /**
     * Build the action form fields from `parameters()`, skipping any that
     * are supplied by the system (present in `prefill()`), and rendering
     * choice inputs via `options()`.
     */
    public function getActionFields(): array
    {
        $fields = [];
        $prefill = $this->prefill();
        $options = $this->options();

        foreach ($this->parameters() as $parameter) {
            $name = $parameter['name'];

            if (array_key_exists($name, $prefill)) {
                continue;
            }

            $description = $parameter['description'] ?? '';
            $type = $parameter['type'] ?? 'string';
            $rules = $parameter['rules'] ?? [];

            $field = $this->createNovaField($name, $type, $rules, $options);

            if ($description) {
                $field = $field->help($description);
            }

            if (! empty($rules)) {
                $field = $field->rules($rules);
            }

            if (in_array('required', $rules, true)) {
                $field = $field->required();
            }

            if (in_array('nullable', $rules, true)) {
                $field = $field->nullable();
            }

            $fields[] = $field;
        }

        return $fields;
    }

    /**
     * Execute the action as a Nova action via the SIPOC pipeline.
     */
    public function asNovaAction(ActionFields $fields, Collection $models): mixed
    {
        try {
            $context = $this->makeNovaContext($fields, $models);
            $extras = $this->novaModelExtras($models);

            $result = $this->execute($fields->toArray(), $context, $extras);

            if ($result->isFail()) {
                return Action::danger($result->message() ?? 'This action cannot be executed.');
            }

            $data = $result->data();
            $message = $data['message'] ?? 'Action completed successfully.';

            return Action::message($message);
        } catch (ValidationException $e) {
            $errors = [];
            foreach ($e->errors() as $field => $messages) {
                $errors[] = "{$field}: ".implode(', ', $messages);
            }

            return Action::danger(implode("\n", $errors));
        } catch (Throwable $e) {
            return Action::danger($e->getMessage());
        }
    }

    /**
     * Build the context object passed to `prefill()` closures.
     */
    protected function makeNovaContext(ActionFields $fields, Collection $models): object
    {
        return new class($fields, $models)
        {
            public function __construct(
                public ActionFields $fields,
                public Collection $models,
            ) {}
        };
    }

    /**
     * Nova convenience: inject the selected models Collection into the
     * validated inputs under a singular or plural snake_case key so
     * `handle()` can access them without declaring them in `parameters()`.
     *
     * @return array<string, mixed>
     */
    protected function novaModelExtras(Collection $models): array
    {
        $label = $this->resolveParameterLabel($models);

        return [$label => $models];
    }

    /**
     * Build a snake_case identifier for the models the action is being run
     * against. Singular for exactly one model, plural otherwise. Empty
     * string when the collection is empty (standalone actions).
     */
    protected function resolveParameterLabel(Collection $models): string
    {
        $first = $models->first();

        if ($first === null) {
            return '';
        }

        $singular = Str::snake(class_basename($first));

        return $models->count() === 1 ? $singular : Str::plural($singular);
    }

    /**
     * Create a Nova field for a user-supplied parameter.
     *
     * @param  array<string, array<int|string, string>>  $options
     */
    protected function createNovaField(string $name, string $type, array $rules, array $options = []): mixed
    {
        $label = str_replace('_', ' ', ucfirst($name));

        if (array_key_exists($name, $options) && ! empty($options[$name])) {
            $field = Select::make($label, $name)
                ->options($options[$name])
                ->displayUsingLabels();
        } elseif ($type === 'array' || (class_exists($type) && $type !== '')) {
            $field = $type === 'array'
                ? KeyValue::make($label, $name)
                : Text::make($label, $name);
        } elseif ($this->fieldFromRule($label, $name, $rules)) {
            $field = $this->fieldFromType($label, $name, $type, $rules);
        } else {
            $field = Text::make($label, $name);
        }

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
     */
    protected function fieldFromRule(string $label, string $name, array $rules): mixed
    {
        foreach ($rules as $rule) {
            $ruleName = is_string($rule) ? explode(':', $rule)[0] : null;

            if ($ruleName === null) {
                continue;
            }

            $field = match ($ruleName) {
                'file', 'mimes', 'mimetypes' => File::make($label, $name),
                'image', 'dimensions' => Image::make($label, $name),
                'date', 'date_format', 'before', 'after', 'before_or_equal', 'after_or_equal' => Date::make($label, $name),
                'email' => Email::make($label, $name),
                'url', 'active_url' => URL::make($label, $name),
                'ip', 'ipv4', 'ipv6' => Text::make($label, $name),
                'mac_address' => Text::make($label, $name),
                'uuid', 'ulid' => Text::make($label, $name),
                'password', 'current_password' => Password::make($label, $name),
                'json' => Code::make($label, $name)->json(),
                'array' => KeyValue::make($label, $name),
                'numeric', 'integer', 'digits', 'digits_between' => Number::make($label, $name),
                'decimal' => Number::make($label, $name)->step(0.01),
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
