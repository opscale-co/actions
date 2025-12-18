<?php

namespace Workbench\App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Auth\PasswordValidationRules;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Password;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Override;
use Workbench\App\Services\Actions\ResetPassword;

/**
 * @extends Resource<\Workbench\App\Models\User>
 */
class User extends Resource
{
    use PasswordValidationRules;

    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\Workbench\App\Models\User>
     */
    public static $model = \Workbench\App\Models\User::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'name';

    /**
     * The columns that should be searched.
     *
     * @var array<int, string>
     */
    public static $search = [
        'id', 'name', 'email',
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @return array<int, \Laravel\Nova\Fields\Field|\Laravel\Nova\Panel|\Laravel\Nova\ResourceTool|\Illuminate\Http\Resources\MergeValue>
     */
    #[Override]
    final public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Name')
                ->sortable()
                ->rules(fn (): array => $this->model()->validationRules['name'] ?? []),

            Text::make('Email')
                ->sortable()
                ->rules('required', 'email', 'max:254')
                ->creationRules(fn (): array => $this->model()->validationRules['email'] ?? [])
                ->updateRules(fn (): array => $this->model()->validationRules['email'] ?? []),

            Password::make('Password')
                ->onlyOnForms()
                ->creationRules(fn (): array => $this->model()->validationRules['password'] ?? [])
                ->updateRules(fn (): array => $this->model()->validationRules['password'] ?? []),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @return array<int, \Laravel\Nova\Card>
     */
    #[Override]
    final public function cards(NovaRequest $request): array
    {
        return parent::cards($request);
    }

    /**
     * Get the filters available for the resource.
     *
     * @return array<int, \Laravel\Nova\Filters\Filter>
     */
    #[Override]
    final public function filters(NovaRequest $request): array
    {
        return parent::filters($request);
    }

    /**
     * Get the lenses available for the resource.
     *
     * @return array<int, \Laravel\Nova\Lenses\Lens>
     */
    #[Override]
    final public function lenses(NovaRequest $request): array
    {
        return parent::lenses($request);
    }

    /**
     * Get the actions available for the resource.
     *
     * @return array<int, \Laravel\Nova\Actions\Action>
     */
    #[Override]
    final public function actions(NovaRequest $request): array
    {
        return [
            ResetPassword::make(),
        ];
    }
}
