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
     */
    public function fields(NovaRequest $request): array
    {
        if ($this->hasMethod('bootNovaContext')) {
            $this->resolveAndCallMethod('bootNovaContext', [
                'request' => $request,
                'models' => $this->resolveSelectedModels($request),
            ]);
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
        if ($this->hasMethod('bootNovaContext')) {
            $this->resolveAndCallMethod('bootNovaContext', [
                'request' => app(NovaRequest::class),
                'models' => $models,
            ]);
        }

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

    /**
     * Resolve the models the user selected on the resource index/detail page.
     *
     * Returns a collection (possibly empty) so the action can populate context
     * with the targeted models before the form fields are even rendered. When
     * the request carries no resource selection (e.g. standalone actions),
     * returns null so the context bag omits the key entirely.
     */
    protected function resolveSelectedModels(NovaRequest $request): ?Collection
    {
        if (! method_exists($request, 'selectedResources')) {
            return null;
        }

        try {
            $models = $request->selectedResources();
        } catch (\Throwable) {
            return null;
        }

        if ($models === null || $models->isEmpty()) {
            return null;
        }

        return $models instanceof Collection ? $models : collect($models);
    }
}
