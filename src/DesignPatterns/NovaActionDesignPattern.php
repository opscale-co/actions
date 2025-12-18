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
        // Nova actions are resolved when:
        // 1. Resource::actions() is called (returns array of actions)
        // 2. ResolvesActions::resolveActions() wraps them in ActionCollection
        //
        // We match when the action is instantiated during the actions() method call
        // on any class that uses the ResolvesActions trait (Nova Resources)
        if ($frame->function !== 'actions') {
            return false;
        }

        $object = $frame->getObject();

        if ($object === null) {
            return false;
        }

        // Check if the object uses ResolvesActions trait (Nova Resources)
        $traits = class_uses_recursive($object);

        return in_array(ResolvesActions::class, $traits, true);
    }

    public function decorate($instance, BacktraceFrame $frame)
    {
        return app(NovaActionDecorator::class, [
            'action' => $instance,
        ]);
    }
}
