<?php

declare(strict_types=1);

namespace Opscale\Actions\Adapters;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsController;
use Throwable;

/**
 * Trait ControllerAdapter
 *
 * Adapts the Action contract to Lorisleiva\Actions\Concerns\AsController via
 * the SIPOC pipeline. Prefill closures receive the current `Request` as
 * their context so they can pull from headers, session, auth, etc.
 *
 * @see AsController
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
        return ['api'];
    }

    /**
     * Execute the action as a controller.
     */
    public function asController(Request $request): JsonResponse
    {
        try {
            $result = $this->execute($request->all(), $request);

            if ($result->isFail()) {
                return response()->json([
                    'success' => false,
                    'message' => $result->message(),
                    'data' => $result->data(),
                ], 422);
            }

            return response()->json([
                'success' => true,
                'data' => $result->data(),
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
