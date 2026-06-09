<?php

declare(strict_types=1);

namespace Opscale\Actions\Decorators;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;
use Lorisleiva\Actions\Concerns\DecorateActions;

class NovaActionDecorator extends Action
{
    use DecorateActions;

    /**
     * Create a new Nova action decorator instance.
     */
    public function __construct($action)
    {
        $this->setAction($action);

        $className = class_basename($action);
        $title = Str::headline($className);
        $uriKey = Str::slug(Str::snake($className));

        $this->name = $this->fromActionMethodOrProperty('getActionTitle', 'actionTitle', $title);
        $this->uriKey = $this->fromActionMethodOrProperty('getActionUriKey', 'actionUriKey', $uriKey);

        $this->canSee(fn () => (bool) $this->resolveAndCallMethod('canRun', []));
    }

    /**
     * Get the fields available on the action.
     *
     * Delegates to the action's getActionFields($request) which populates
     * context internally — so prefill() can read the active request, user,
     * and selected models before deciding which fields to render.
     */
    public function fields(NovaRequest $request): array
    {
        if ($this->hasMethod('getActionFields')) {
            return $this->resolveAndCallMethod('getActionFields', ['request' => $request]);
        }

        return $this->fromActionMethodOrProperty('getActionFields', 'actionFields', []);
    }

    /**
     * Perform the action on the given models.
     *
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        // Call asNovaAction if it exists, passing the ActionFields and models
        if ($this->hasMethod('asNovaAction')) {
            return $this->resolveAndCallMethod('asNovaAction', [
                'fields' => $fields,
                'models' => $models,
            ]);
        }

        // Fall back to handle, passing the fields as array
        if ($this->hasMethod('handle')) {
            $attributes = $fields->toArray();

            if ($models->count() === 1) {
                $attributes['model'] = $models->first();
            } else {
                $attributes['models'] = $models;
            }

            $result = $this->resolveAndCallMethod('handle', $attributes);

            if (empty($result)) {
                return Action::danger('Something went wrong, please try again in 15 minutes.');
            }

            return Action::message('Operation completed successfully.');
        }
    }
}
