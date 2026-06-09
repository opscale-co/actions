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
use Laravel\Nova\Http\Requests\NovaRequest;
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
     * Current Nova request, populated by the decorator before delegation.
     */
    public ?NovaRequest $novaRequest = null;

    /**
     * Models the action is being run against, populated by the decorator.
     *
     * @var Collection<int, mixed>|null
     */
    public ?Collection $models = null;

    /**
     * Inject Nova context onto the action before the adapter executes.
     *
     * Populates the shared {@see Opscale\Actions\Action::context()} bag with
     * the values declared by {@see novaContext()} so {@see prefill()} can
     * read them. Called automatically by both {@see getActionFields()} (at
     * field-render time) and {@see asNovaAction()} (at execution time) so
     * context is ready in either phase, regardless of how the action is
     * invoked (through the decorator or directly).
     */
    public function bootNovaContext(?NovaRequest $request = null, ?Collection $models = null): void
    {
        $this->novaRequest = $request;
        $this->models = $models;

        $this->context = $this->novaContext();
    }

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
     *
     * Populates the execution context first so {@see prefill()} can compute
     * values from the active request, user, and selected models — context
     * MUST be available before fields are decided, not just at handle time.
     */
    public function getActionFields(?NovaRequest $request = null): array
    {
        $request = $request ?? app(NovaRequest::class);
        $this->bootNovaContext($request, $this->resolveSelectedModels($request));

        $fields = [];
        $prefill = $this->prefill();
        $options = $this->options();

        foreach ($this->parameters() as $parameter) {
            $name = $parameter['name'];

            // Prefilled parameters are not solicited from the user.
            if (array_key_exists($name, $prefill)) {
                continue;
            }

            $description = $parameter['description'] ?? '';
            $type = $parameter['type'] ?? 'string';
            $rules = $parameter['rules'] ?? [];

            // Create the appropriate Nova field based on type
            $field = $this->createNovaField($name, $type, $rules, $options[$name] ?? null);

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
            $this->bootNovaContext(
                $this->novaRequest ?? app(NovaRequest::class),
                $models,
            );

            $attributes = array_merge($fields->toArray(), $this->prefill());

            $this->fill($attributes);
            $validatedData = $this->validateAttributes();

            $label = $this->resolveParameterLabel($models);
            $validatedData[$label] = $models;

            $result = $this->handle($validatedData);

            if (empty($result)) {
                return Action::danger('Something went wrong while executing the action.');
            }

            $message = $result['message'] ?? 'Action completed successfully.';

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
     * Declare the context values this adapter publishes into the shared
     * {@see Opscale\Actions\Action::context()} bag.
     *
     * @return array<string, mixed>
     */
    protected function novaContext(): array
    {
        return array_filter([
            'request' => $this->novaRequest,
            'user' => $this->novaRequest?->user(),
            'resource' => $this->resolveResourceClass($this->novaRequest),
            'models' => $this->models,
        ]);
    }

    /**
     * Resolve the Nova Resource class the action is being invoked from.
     *
     * Returns the resource class-string (e.g. `App\Nova\User`) or null when
     * the request has no resource binding — for instance during standalone
     * actions or when the lookup aborts because the resource key is missing.
     */
    protected function resolveResourceClass(?NovaRequest $request): ?string
    {
        if ($request === null || ! method_exists($request, 'resource')) {
            return null;
        }

        try {
            return $request->resource();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Resolve the models the user selected on the resource index/detail page
     * so they are available to {@see prefill()} during field rendering.
     */
    protected function resolveSelectedModels(?NovaRequest $request): ?Collection
    {
        if ($request === null || ! method_exists($request, 'selectedResources')) {
            return null;
        }

        try {
            $models = $request->selectedResources();
        } catch (Throwable) {
            return null;
        }

        if ($models === null) {
            return null;
        }

        $collection = $models instanceof Collection ? $models : collect($models);

        return $collection->isEmpty() ? null : $collection;
    }

    /**
     * Build a snake_case identifier for the models the action is being run against.
     *
     * Singular form when exactly one model is selected (e.g. `user`, `order_item`);
     * plural otherwise (e.g. `users`, `order_items`). Returns an empty string when
     * the action is run with no targeted models (standalone actions).
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
     * Create a Nova field based on the parameter type and rules.
     */
    protected function createNovaField(string $name, string $type, array $rules, ?array $options = null): mixed
    {
        $label = str_replace('_', ' ', ucfirst($name));

        if (! empty($options)) {
            $field = Select::make($label, $name)
                ->options($this->toSelectOptions($options))
                ->displayUsingLabels();
        } elseif ($type === 'array' || class_exists($type)) {
            $field = $type === 'array'
                ? KeyValue::make($label, $name)
                : Text::make($label, $name);
        } elseif ($field = $this->fieldFromRule($label, $name, $rules)) {
            $field = $this->fieldFromType($label, $name, $type, $rules);
        } else {
            $field = Text::make($label, $name);
        }

        $field->rules($rules);

        return $field;
    }

    /**
     * Normalize an options list into Nova's expected {value: label} shape.
     *
     * Accepts either a list (['active', 'inactive']) or a map
     * (['active' => 'Active']) and always returns a map.
     */
    protected function toSelectOptions(array $options): array
    {
        if (array_is_list($options)) {
            return array_combine($options, array_map(
                fn ($value): string => Str::headline((string) $value),
                $options
            ));
        }

        return $options;
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
