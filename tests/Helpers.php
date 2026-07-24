<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Opscale\Actions\Tests\Fixtures\SipocProbeAction;

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
