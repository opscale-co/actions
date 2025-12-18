<?php

namespace Opscale\Actions\Concerns;

use Laravel\Nova\Actions\ActionResponse;

/**
 * Trait AsNovaAction
 *
 * Provides Nova action functionality for standalone action classes.
 * Use this trait when you want to use an action as a Nova action
 * without extending the base Action class.
 *
 * This trait should be used alongside AsAction from laravel-actions.
 *
 * @see \Opscale\Actions\Decorators\NovaActionDecorator
 * @see \Opscale\Actions\DesignPatterns\NovaActionDesignPattern
 */
trait AsNovaAction
{
    /**
     * Return a success message response.
     */
    public function message(string $message): ActionResponse
    {
        return ActionResponse::message($message);
    }

    /**
     * Return a danger/error message response.
     */
    public function danger(string $message): ActionResponse
    {
        return ActionResponse::danger($message);
    }

    /**
     * Return a deleted response.
     */
    public function deleted(): ActionResponse
    {
        return ActionResponse::deleted();
    }

    /**
     * Return a redirect response.
     */
    public function redirect(string $url, bool $openInNewTab = false): ActionResponse
    {
        $response = ActionResponse::redirect($url);

        if ($openInNewTab) {
            $response = $response->openInNewTab();
        }

        return $response;
    }

    /**
     * Return a visit response (Inertia navigation).
     */
    public function visit(string $path, array $options = []): ActionResponse
    {
        return ActionResponse::visit($path, $options);
    }

    /**
     * Return a download response.
     */
    public function download(string $name, string $url): ActionResponse
    {
        return ActionResponse::download($name, $url);
    }

    /**
     * Return a modal response.
     */
    public function modal(string $modal, array $data = []): ActionResponse
    {
        return ActionResponse::modal($modal, $data);
    }

    /**
     * Emit an event response.
     */
    public function emit(string $event, array $data = []): ActionResponse
    {
        return ActionResponse::emit($event, $data);
    }

    /**
     * Return a push response (navigate to a resource).
     */
    public function push(string $resource, mixed $id): ActionResponse
    {
        return ActionResponse::push([
            'name' => 'detail',
            'params' => [
                'resourceName' => $resource,
                'resourceId' => $id,
            ],
        ]);
    }
}
