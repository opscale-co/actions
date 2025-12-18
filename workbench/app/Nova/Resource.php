<?php

namespace Workbench\App\Nova;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource as NovaResource;
use Override;

/**
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends NovaResource<TModel>
 */
abstract class Resource extends NovaResource
{
    /**
     * Build an "index" query for the given resource.
     */
    #[Override]
    final public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        return $query;
    }

    /**
     * Build a "detail" query for the given resource.
     */
    #[Override]
    final public static function detailQuery(NovaRequest $request, Builder $query): Builder
    {
        return parent::detailQuery($request, $query);
    }

    /**
     * Build a "relatable" query for the given resource.
     *
     * This query determines which instances of the model may be attached to other resources.
     */
    #[Override]
    final public static function relatableQuery(NovaRequest $request, Builder $query): Builder
    {
        return parent::relatableQuery($request, $query);
    }
}
