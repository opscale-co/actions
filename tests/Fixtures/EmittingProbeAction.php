<?php

declare(strict_types=1);

namespace Opscale\Actions\Tests\Fixtures;

use Opscale\Actions\Concerns\EmitsEvent;

/**
 * Trait-marked variant of the probe action — used to assert that the
 * pipeline dispatches `opscale.action.{identifier}` after each success.
 */
class EmittingProbeAction extends SipocProbeAction
{
    use EmitsEvent;

    public function identifier(): string
    {
        return 'emitting-probe';
    }
}
