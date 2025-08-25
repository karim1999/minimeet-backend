<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

abstract class ApiController extends Controller
{
    /**
     * Return a successful JSON response.
     */
    protected function respondWithSuccess($data = null, string $message = '', int $code = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    /**
     * Return an error JSON response.
     */
    protected function respondWithError(string $message, int $code = 400, array $errors = []): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (! empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * Return a not found JSON response.
     */
    protected function respondNotFound(string $message = 'Resource not found'): JsonResponse
    {
        return $this->respondWithError($message, 404);
    }

    /**
     * Return a validation error JSON response.
     */
    protected function respondValidationError(array $errors, string $message = 'Validation failed'): JsonResponse
    {
        return $this->respondWithError($message, 422, $errors);
    }

    /**
     * Return a server error JSON response.
     */
    protected function respondServerError(string $message = 'Internal server error'): JsonResponse
    {
        return $this->respondWithError($message, 500);
    }

    /**
     * Return a created resource JSON response.
     */
    protected function respondCreated($data = null, string $message = 'Resource created successfully'): JsonResponse
    {
        return $this->respondWithSuccess($data, $message, 201);
    }

    /**
     * Return an unauthorized JSON response.
     */
    protected function respondUnauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->respondWithError($message, 401);
    }

    /**
     * Return a forbidden JSON response.
     */
    protected function respondForbidden(string $message = 'Forbidden'): JsonResponse
    {
        return $this->respondWithError($message, 403);
    }

    /**
     * Return a bad request JSON response.
     */
    protected function respondBadRequest(string $message = 'Bad request'): JsonResponse
    {
        return $this->respondWithError($message, 400);
    }

    /**
     * Log an error and return a server error response.
     */
    protected function logErrorAndRespond(\Throwable $exception, string $context = ''): JsonResponse
    {
        Log::error('API Error: '.$context, [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ]);

        return $this->respondServerError();
    }
}
