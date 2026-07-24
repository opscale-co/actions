<?php

declare(strict_types=1);

namespace Opscale\Actions\Adapters;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Opscale\Actions\Decorators\MCPToolDecorator;
use Throwable;

/**
 * Trait MCPToolAdapter
 *
 * Adapts the Action contract to an MCP Tool via the SIPOC pipeline.
 *
 * - identifier() → tool name
 * - name()       → tool title
 * - description()→ tool description
 * - parameters() → inputSchema (skipping prefill()'d entries; enum lists
 *                  emitted from options())
 * - outputs()    → outputSchema
 *
 * @see MCPToolDecorator
 */
trait MCPToolAdapter
{
    /**
     * Whether the tool should be registered.
     */
    public bool $shouldRegisterTool = true;

    public function getToolName(): string
    {
        return $this->identifier();
    }

    public function getToolTitle(): string
    {
        return $this->name();
    }

    public function getToolDescription(): string
    {
        return $this->description();
    }

    /**
     * Build the tool input schema from parameters(), skipping prefill()'d
     * entries and rendering `enum` lists from options().
     */
    public function getToolSchema(JsonSchema $schema): array
    {
        $properties = [];
        $prefill = $this->prefill();
        $options = $this->options();

        foreach ($this->parameters() as $parameter) {
            $name = $parameter['name'];

            if (array_key_exists($name, $prefill)) {
                continue;
            }

            $type = $parameter['type'] ?? 'string';
            $description = $parameter['description'] ?? '';
            $rules = $parameter['rules'] ?? [];
            $isRequired = $this->isParameterRequiredForSchema($parameter);

            $property = $this->createSchemaProperty($schema, $type);

            if ($description) {
                $property = $property->description($description);
            }

            $choices = $this->resolveChoices($name, $options, $rules);
            if ($choices !== []) {
                $property = $property->enum($choices);
            }

            if ($isRequired) {
                $property = $property->required();
            }

            $properties[$name] = $property;
        }

        return $properties;
    }

    /**
     * Build the tool output schema from outputs().
     */
    public function getToolOutputSchema(JsonSchema $schema): array
    {
        $properties = [];

        foreach ($this->outputs() as $output) {
            $type = $output['type'] ?? 'string';
            $description = $output['description'] ?? '';
            $rules = $output['rules'] ?? [];

            $property = $this->createSchemaProperty($schema, $type);

            if ($description) {
                $property = $property->description($description);
            }

            if (in_array('required', $rules, true)) {
                $property = $property->required();
            }

            $properties[$output['name']] = $property;
        }

        return $properties;
    }

    /**
     * Execute the action as an MCP tool via the SIPOC pipeline.
     */
    public function asMCPTool(Request $request): Response
    {
        try {
            $result = $this->execute($request->toArray() ?? [], $request);

            if ($result->isFail()) {
                return Response::error($result->message() ?? 'This action cannot be executed.');
            }

            return Response::text(json_encode($result->data(), JSON_PRETTY_PRINT));
        } catch (ValidationException $e) {
            $errors = [];
            foreach ($e->errors() as $field => $messages) {
                $errors[] = "{$field}: ".implode(', ', $messages);
            }

            return Response::error(implode("\n", $errors));
        } catch (Throwable $e) {
            return Response::error($e->getMessage());
        }
    }

    /**
     * Create a schema property based on the parameter type.
     */
    protected function createSchemaProperty(JsonSchema $schema, string $type): mixed
    {
        if (class_exists($type)) {
            return $schema->string();
        }

        return match ($type) {
            'int', 'integer' => $schema->integer(),
            'float', 'double', 'number' => $schema->number(),
            'bool', 'boolean' => $schema->boolean(),
            'array' => $schema->array(),
            'object' => $schema->object(),
            default => $schema->string(),
        };
    }

    /**
     * Determine the enum values for a schema property.
     *
     * Prefers `options()` (explicit, per-parameter). Falls back to an `in:`
     * validation rule if no options are declared for the parameter — kept
     * for backwards convenience.
     *
     * @param  array<string, array<int|string, string>>  $options
     * @param  array<int, mixed>  $rules
     * @return array<int, string>
     */
    protected function resolveChoices(string $name, array $options, array $rules): array
    {
        if (array_key_exists($name, $options) && ! empty($options[$name])) {
            $choices = $options[$name];

            return array_is_list($choices) ? $choices : array_keys($choices);
        }

        foreach ($rules as $rule) {
            if (is_string($rule) && str_starts_with($rule, 'in:')) {
                return explode(',', substr($rule, 3));
            }
        }

        return [];
    }

    protected function isParameterRequiredForSchema(array $parameter): bool
    {
        $rules = $parameter['rules'] ?? [];

        return in_array('required', $rules, true);
    }
}
