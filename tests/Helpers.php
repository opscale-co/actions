<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\File;
use Opscale\Actions\Tests\Fixtures\SipocProbeAction;

/**
 * @param  array<string, mixed>  $parameter
 */
function novaInferredField(array $parameter): Field
{
    $action = new SipocProbeAction;
    $action->paramsSpec = [$parameter];

    $field = $action->getActionFields()[0];
    assert($field instanceof Field);

    return $field;
}

/**
 * @param  array<int, string>  $rules
 */
function novaFileField(array $rules): File
{
    $field = novaInferredField(['name' => 'attachment', 'type' => 'string', 'rules' => $rules]);
    assert($field instanceof File);

    return $field;
}

function canRunResponse(SipocProbeAction $action, array $body): array
{
    return $action->asController(Request::create('/', 'POST', $body))->getData(true);
}

function softFailingAction(): SipocProbeAction
{
    $action = new SipocProbeAction;
    $action->handleImpl = fn (array $inputs): array => $action->fail('Cannot proceed.', ['reason_code' => 42]);

    return $action;
}

function hardFailingAction(): SipocProbeAction
{
    $action = new SipocProbeAction;
    $action->handleImpl = function (): array {
        throw new RuntimeException('Boom!');
    };

    return $action;
}
