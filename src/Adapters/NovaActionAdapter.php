<?php

declare(strict_types=1);

namespace Opscale\Actions\Adapters;

use BackedEnum;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\Color;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Email;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Fields\Image;
use Laravel\Nova\Fields\KeyValue;
use Laravel\Nova\Fields\MultiSelect;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Password;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\URL;
use Opscale\Actions\Decorators\NovaActionDecorator;
use RuntimeException;
use Throwable;

/**
 * Trait NovaActionAdapter
 *
 * Adapts the Action contract to Nova Action.
 *
 * - name()       → getActionTitle()
 * - identifier() → getActionUriKey()
 * - parameters() → getActionFields() (skipping prefill()'d ones; using
 *                  options(), the per-parameter `options` key, or the
 *                  validation rules to pick the field type)
 * - outputs()    → validated after handle(); user-facing summary shown via
 *                  NovaAction::message()
 *
 * Prefill closures receive an anonymous context object exposing `->fields`
 * (the submitted ActionFields) and `->models` (the selected model
 * Collection). The pipeline injects the models Collection into `handle()`'s
 * inputs under a snake_case key (`user`, `users`, `order_items`, ...) as a
 * Nova convenience. Uploaded files are stored after validation and reach
 * `handle()` as path strings.
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
     * choice inputs via `options()` or the per-parameter `options` key.
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

            $field = $this->createNovaField($name, $type, $rules, $options, $parameter['options'] ?? null, $parameter['label'] ?? null);

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

            $result = $this->execute(
                $fields->toArray(),
                $context,
                $extras,
                fn (array $validated): array => $this->storeUploadedFiles($validated),
            );

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
                /** @var Collection<int, mixed> */
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
     * Store any UploadedFile values in the validated bag and substitute the
     * stored path string, so `handle()` keeps receiving scalars. Runs after
     * validation — `file`/`mimes:` rules validate the real UploadedFile.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function storeUploadedFiles(array $validated): array
    {
        foreach ($validated as $key => $value) {
            if ($value instanceof UploadedFile) {
                $path = $value->store($this->identifier());

                if ($path === false) {
                    throw new RuntimeException("Failed to store uploaded file for parameter '{$key}'.");
                }

                $validated[$key] = $path;
            }
        }

        return $validated;
    }

    /**
     * Create a Nova field for a user-supplied parameter.
     *
     * Precedence: `options()` map → per-parameter `options` spec →
     * validation rules → primitive type (rules are more specific than the
     * primitive type, so they win). The label is the per-parameter `label`
     * when given (verbatim — the author already translated it), otherwise
     * the humanized name passed through the host translator.
     *
     * @param  array<int, mixed>  $rules
     * @param  array<string, array<int|string, string>>  $options
     */
    protected function createNovaField(string $name, string $type, array $rules, array $options = [], mixed $parameterOptions = null, ?string $label = null): Field
    {
        $label ??= $this->translateParameterLabel($name);

        if (array_key_exists($name, $options) && ! empty($options[$name])) {
            return $this->selectFromParameterOptions($label, $name, $type, $options[$name]);
        }

        if ($parameterOptions !== null) {
            return $this->selectFromParameterOptions($label, $name, $type, $parameterOptions);
        }

        return $this->fieldFromRule($label, $name, $rules)
            ?? $this->fieldFromType($label, $name, $type, $rules);
    }

    /**
     * Humanize the parameter name and pass it through the host translator,
     * so a JSON lang entry keyed by the humanized name ("Assessment types"
     * => "Tipos de prueba") localizes the modal. Without a translation the
     * humanized name is returned unchanged.
     */
    final protected function translateParameterLabel(string $name): string
    {
        $humanized = str_replace('_', ' ', ucfirst($name));
        $translated = Lang::get($humanized);

        return is_string($translated) ? $translated : $humanized;
    }

    /**
     * Build a Select (MultiSelect for `array` parameters) from a
     * per-parameter `options` spec: a Closure resolved lazily at render
     * time, a literal [value => label] map, or a backed-enum class-string
     * (cases mapped value => name). Never searchable — the searchable
     * Select does not render a native <select>.
     */
    protected function selectFromParameterOptions(string $label, string $name, string $type, mixed $spec): Field
    {
        $field = $type === 'array' ? MultiSelect::make($label, $name) : Select::make($label, $name);

        if ($spec instanceof Closure) {
            $field->options(function () use ($spec): array {
                /** @var array<int|string, string> $resolved */
                $resolved = (array) $spec();

                return $resolved;
            });
        } elseif (is_array($spec)) {
            /** @var array<int|string, string> $spec */
            $field->options($spec);
        } elseif (is_string($spec) && enum_exists($spec) && is_subclass_of($spec, BackedEnum::class)) {
            $field->options(function () use ($spec): array {
                $cases = [];

                foreach ($spec::cases() as $backedEnum) {
                    $cases[$backedEnum->value] = $backedEnum->name;
                }

                return $cases;
            });
        } else {
            throw new InvalidArgumentException(
                "Invalid 'options' spec for parameter '{$name}': expected Closure, array, or backed-enum class-string.",
            );
        }

        return $field->displayUsingLabels();
    }

    /**
     * Create a Nova field based on the parameter type.
     *
     * @param  array<int, mixed>  $rules
     */
    protected function fieldFromType(string $label, string $name, string $type, array $rules): Field
    {
        if ($type !== '' && class_exists($type)) {
            return Text::make($label, $name);
        }

        $maxLength = $this->extractMaxLengthFromRules($rules);
        $minLength = $this->extractMinLengthFromRules($rules);

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
            'array', 'keyvalue' => KeyValue::make($label, $name),
            default => ($maxLength !== null && $maxLength > 255) || ($maxLength === null && $minLength !== null && $minLength >= 10)
                ? Textarea::make($label, $name)
                : Text::make($label, $name),
        };
    }

    /**
     * Create a Nova field based on validation rules. Only string rules are
     * inspected; rule objects fall through to type-based inference.
     *
     * @param  array<int, mixed>  $rules
     */
    protected function fieldFromRule(string $label, string $name, array $rules): ?Field
    {
        $stringRules = array_values(array_filter($rules, is_string(...)));

        // Choice-producing rules win regardless of their position, so a
        // parameter like ['required', 'uuid', 'exists:users,id'] renders a
        // Select from the referenced table, not an id input.
        foreach ($stringRules as $stringRule) {
            if (str_starts_with($stringRule, 'in:')) {
                $values = explode(',', substr($stringRule, 3));

                return Select::make($label, $name)
                    ->options(array_combine($values, $values))
                    ->displayUsingLabels();
            }

            if (str_starts_with($stringRule, 'exists:')) {
                $parts = explode(',', substr($stringRule, 7));
                $table = $parts[0];
                $keyColumn = $parts[1] ?? 'id';

                return Select::make($label, $name)
                    ->options(fn (): array => DB::table($table)->pluck('name', $keyColumn)->all())
                    ->displayUsingLabels();
            }
        }

        // A uuid/ulid parameter with no options source is a definition
        // error — the operator must never type a raw id. Render an empty
        // Select so the gap is visible instead of a free-text id input.
        foreach ($stringRules as $stringRule) {
            $ruleName = explode(':', $stringRule)[0];

            if (in_array($ruleName, ['uuid', 'ulid'], true)) {
                Log::warning(
                    "Parameter '{$name}' has a {$ruleName} rule but no options source "
                    ."(options(), a per-parameter 'options' key, or an exists: rule); rendering an empty Select.",
                );

                return Select::make($label, $name)
                    ->options([])
                    ->displayUsingLabels();
            }
        }

        foreach ($stringRules as $stringRule) {
            $ruleName = explode(':', $stringRule)[0];

            $field = match ($ruleName) {
                'file', 'mimes', 'mimetypes' => $this->fileFieldFromRules($label, $name, $stringRules),
                'image', 'dimensions' => Image::make($label, $name),
                'date', 'date_format', 'before', 'after', 'before_or_equal', 'after_or_equal' => $this->isDateTimeParameter($name, $stringRules)
                    ? DateTime::make($label, $name)
                    : Date::make($label, $name),
                'email' => Email::make($label, $name),
                'url', 'active_url' => URL::make($label, $name),
                'ip', 'ipv4', 'ipv6' => Text::make($label, $name),
                'mac_address' => Text::make($label, $name),
                'password', 'current_password' => Password::make($label, $name),
                'json' => Code::make($label, $name)->json(),
                'array' => KeyValue::make($label, $name),
                'numeric', 'integer', 'digits', 'digits_between' => $this->isMonetaryParameter($name, $stringRules)
                    ? Currency::make($label, $name)
                    : Number::make($label, $name),
                'decimal' => $this->isMonetaryParameter($name, $stringRules)
                    ? Currency::make($label, $name)
                    : Number::make($label, $name)->step(0.01),
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
     * Build a File or Image field from file rules, deriving
     * `acceptedTypes` from `mimes:` extensions (dot-prefixed) and raw
     * `mimetypes:` values.
     *
     * @param  array<int, string>  $rules
     */
    protected function fileFieldFromRules(string $label, string $name, array $rules): Field
    {
        $extensions = [];
        $mimetypes = [];
        $isImage = false;

        foreach ($rules as $rule) {
            if (str_starts_with($rule, 'mimes:')) {
                $extensions = array_merge($extensions, explode(',', substr($rule, 6)));
            } elseif (str_starts_with($rule, 'mimetypes:')) {
                $mimetypes = array_merge($mimetypes, explode(',', substr($rule, 10)));
            } elseif (in_array(explode(':', $rule)[0], ['image', 'dimensions'], true)) {
                $isImage = true;
            }
        }

        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'avif', 'heic'];
        $values = array_merge($extensions, $mimetypes);

        if (! $isImage && $values !== []) {
            $isImage = true;

            foreach ($values as $value) {
                if (! in_array($value, $imageExtensions, true) && ! str_starts_with($value, 'image/')) {
                    $isImage = false;
                    break;
                }
            }
        }

        $field = $isImage ? Image::make($label, $name) : File::make($label, $name);

        $accepted = array_merge(
            array_map(fn (string $extension): string => '.'.$extension, $extensions),
            $mimetypes,
        );

        if ($accepted !== []) {
            $field->acceptedTypes(implode(',', $accepted));
        }

        return $field;
    }

    /**
     * A date parameter renders as DateTime when its name ends in `_at` or
     * a `date_format:` rule carries time tokens; Date otherwise.
     *
     * @param  array<int, string>  $rules
     */
    protected function isDateTimeParameter(string $name, array $rules): bool
    {
        if (str_ends_with($name, '_at')) {
            return true;
        }

        foreach ($rules as $rule) {
            if (str_starts_with($rule, 'date_format:')
                && preg_match('/[HGhgisuvaA]/', substr($rule, 12)) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * A numeric parameter renders as Currency when its rules carry
     * `decimal:2`, or `min:0` combined with a monetary snake_case segment
     * in the parameter name.
     *
     * @param  array<int, string>  $rules
     */
    protected function isMonetaryParameter(string $name, array $rules): bool
    {
        if (in_array('decimal:2', $rules, true)) {
            return true;
        }

        if (! in_array('min:0', $rules, true)) {
            return false;
        }

        $monetary = [
            'price', 'amount', 'cost', 'fee', 'fees', 'total', 'subtotal', 'discount',
            'tax', 'balance', 'salary', 'wage', 'wages', 'payment', 'refund', 'deposit',
            'revenue', 'budget', 'charge', 'commission',
        ];

        return array_intersect(explode('_', $name), $monetary) !== [];
    }

    /**
     * Extract max length from validation rules.
     *
     * @param  array<int, mixed>  $rules
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

    /**
     * Extract min length from validation rules.
     *
     * @param  array<int, mixed>  $rules
     */
    protected function extractMinLengthFromRules(array $rules): ?int
    {
        foreach ($rules as $rule) {
            if (is_string($rule) && str_starts_with($rule, 'min:')) {
                return (int) substr($rule, 4);
            }
        }

        return null;
    }
}
