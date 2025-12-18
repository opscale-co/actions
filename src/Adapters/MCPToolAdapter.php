<?php

namespace Opscale\Actions\Adapters;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Throwable;

/**
 * Trait MCPToolAdapter
 *
 * Adapts the Action contract to MCP Tool.
 * This trait maps the Action's abstract methods to the tool properties:
 *
 * - identifier() → getToolName()
 * - name() → getToolTitle()
 * - description() → getToolDescription()
 * - parameters() → getToolSchema()
 *
 * @see \Opscale\Actions\Decorators\MCPToolDecorator
 */
trait MCPToolAdapter
{
    /**
     * Whether the tool should be registered.
     */
    public bool $shouldRegisterTool = true;

    /**
     * Get the tool name (identifier).
     */
    public function getToolName(): string
    {
        return $this->identifier();
    }

    /**
     * Get the tool title (human-readable name).
     */
    public function getToolTitle(): string
    {
        return $this->name();
    }

    /**
     * Get the tool description.
     */
    public function getToolDescription(): string
    {
        return $this->description();
    }

    /**
     * Get the tool schema built from parameters().
     */
    public function getToolSchema(JsonSchema $schema): array
    {
        $properties = [];

        foreach ($this->parameters() as $parameter) {
            $name = $parameter['name'];
            $type = $parameter['type'] ?? 'string';
            $description = $parameter['description'] ?? '';
            $rules = $parameter['rules'] ?? [];
            $isRequired = $this->isParameterRequiredForSchema($parameter);

            // Create the schema property based on type
            $property = $this->createSchemaProperty($schema, $type);

            // Add description
            if ($description) {
                $property = $property->description($description);
            }

            // Add enum if 'in:' rule exists
            $choices = $this->extractChoicesFromRulesForSchema($rules);
            if (! empty($choices)) {
                $property = $property->enum($choices);
            }

            // Mark as required if needed
            if ($isRequired) {
                $property = $property->required();
            }

            $properties[$name] = $property;
        }

        return $properties;
    }

    /**
     * Execute the action as an MCP tool.
     */
    public function asMCPTool(Request $request): Response
    {
        try {
            $arguments = $request->toArray() ?? [];

            $this->fill($arguments);
            $validatedData = $this->validateAttributes();

            $result = $this->handle($validatedData);

            if (empty($result)) {
                return Response::error('Something went wrong while executing the tool.');
            }

            return Response::text(json_encode($result, JSON_PRETTY_PRINT));
        } catch (ValidationException $e) {
            $errors = [];
            foreach ($e->errors() as $field => $messages) {
                $errors[] = "{$field}: " . implode(', ', $messages);
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
     * Extract choices from validation rules for schema.
     */
    protected function extractChoicesFromRulesForSchema(array $rules): array
    {
        foreach ($rules as $rule) {
            if (is_string($rule) && str_starts_with($rule, 'in:')) {
                return explode(',', substr($rule, 3));
            }
        }

        return [];
    }

    /**
     * Determine if a parameter is required for schema.
     */
    protected function isParameterRequiredForSchema(array $parameter): bool
    {
        $rules = $parameter['rules'] ?? [];

        return in_array('required', $rules, true);
    }
}
