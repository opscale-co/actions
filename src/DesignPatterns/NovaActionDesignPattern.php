<?php

namespace Opscale\Actions\DesignPatterns;

use Laravel\Nova\ResolvesActions;
use Lorisleiva\Actions\BacktraceFrame;
use Lorisleiva\Actions\DesignPatterns\DesignPattern;
use Opscale\Actions\Concerns\AsNovaAction;
use Opscale\Actions\Decorators\NovaActionDecorator;

class NovaActionDesignPattern extends DesignPattern
{
    public function getTrait(): string
    {
        return AsNovaAction::class;
    }

    public function recognizeFrame(BacktraceFrame $frame): bool
    {
        if ($frame->function !== 'actions') {
            return false;
        }

        $object = $frame->getObject();

        if ($object === null) {
            return false;
        }

        // Check if the object uses ResolvesActions trait (Nova Resources)
        $traits = class_uses_recursive($object);

        if (! in_array(ResolvesActions::class, $traits, true)) {
            return false;
        }

        // Only apply when the action is directly called inside actions(),
        // not when resolved from a helper method (e.g. renderTemplateActions()).
        // We check that no other method on the same resource class sits between
        // the action resolution (identifyAndDecorate) and the actions() frame.
        $resourceClass = get_class($object);
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        $pastIdentify = false;

        foreach ($backtrace as $bt) {
            if (! $pastIdentify) {
                if (($bt['function'] ?? null) === 'identifyAndDecorate') {
                    $pastIdentify = true;
                }

                continue;
            }

            $btClass = $bt['class'] ?? null;
            $btFunction = $bt['function'] ?? null;

            // We reached the actions() frame — it's a direct call.
            if ($btClass === $resourceClass && $btFunction === 'actions') {
                return true;
            }

            // Another method on the same resource sits between resolve and actions()
            // (e.g. renderTemplateActions()), so this is not a direct call.
            if ($btClass === $resourceClass) {
                return false;
            }
        }

        return true;
    }

    public function decorate($instance, BacktraceFrame $frame)
    {
        return app(NovaActionDecorator::class, [
            'action' => $instance,
        ]);
    }
}
