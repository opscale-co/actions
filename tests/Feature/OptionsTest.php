<?php

declare(strict_types=1);

use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Nova\Fields\Select;
use Opscale\Actions\Tests\Fixtures\SipocProbeAction;

it('renders a Nova Select field when options() declares choices for the parameter', function (): void {
    $action = new SipocProbeAction;
    $action->paramsSpec = [
        ['name' => 'status', 'type' => 'string', 'rules' => ['required', 'string']],
    ];
    $action->optionsSpec = ['status' => ['active', 'inactive']];

    $fields = $action->getActionFields();

    expect($fields)->toHaveCount(1)
        ->and($fields[0])->toBeInstanceOf(Select::class);
});

it('emits an enum in the MCP JSON schema from options()', function (): void {
    $action = new SipocProbeAction;
    $action->paramsSpec = [
        ['name' => 'status', 'type' => 'string', 'rules' => ['required']],
    ];
    $action->optionsSpec = ['status' => ['active', 'inactive']];

    $properties = $action->getToolSchema(new JsonSchemaTypeFactory);

    $encoded = json_encode($properties['status']->toArray());

    expect($encoded)->toContain('active')->toContain('inactive');
});

it('still falls back to `in:` rules when options() does not declare choices', function (): void {
    $action = new SipocProbeAction;
    $action->paramsSpec = [
        ['name' => 'status', 'type' => 'string', 'rules' => ['required', 'in:a,b,c']],
    ];
    $action->optionsSpec = [];

    $properties = $action->getToolSchema(new JsonSchemaTypeFactory);
    $encoded = json_encode($properties['status']->toArray());

    expect($encoded)->toContain('a')->toContain('b')->toContain('c');
});
