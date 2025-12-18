<?php

namespace Opscale\Actions\Adapters;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Trait ControllerAdapter
 *
 * Adapts the Action contract to Lorisleiva\Actions\Concerns\AsController.
 * This trait provides controller-specific functionality for actions.
 *
 * @see \Lorisleiva\Actions\Concerns\AsController
 */
trait ControllerAdapter
{
    /**
     * Get the middleware for the controller.
     *
     * @return array<string>
     */
    public function getControllerMiddleware(): array
    {
        return [/*'api'*/];
    }

    /**
     * Execute the action as a controller.
     *
     * Fills attributes from the request, validates them,
     * and handles exceptions gracefully.
     */
    public function asController(Request $request): JsonResponse
    {
        try {
            $this->fillFromRequest($request);
            $validatedData = $this->validateAttributes();

            $result = $this->handle($validatedData);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
