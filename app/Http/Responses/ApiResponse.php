<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    /**
     * Create a successful API response.
     */
    public static function success(
        string $message = 'Operation successful',
        mixed $data = null,
        int $status = 200,
        array $meta = []
    ): JsonResponse {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => array_merge([
                'timestamp' => now()->toISOString(),
                'version' => 'v1',
            ], $meta),
        ];

        return response()->json($response, $status);
    }

    /**
     * Create an error API response.
     */
    public static function error(
        string $message = 'An error occurred',
        mixed $errors = null,
        int $status = 400,
        ?string $errorCode = null,
        array $meta = []
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'meta' => array_merge([
                'timestamp' => now()->toISOString(),
                'error_code' => $errorCode,
            ], $meta),
        ];

        // Remove null values from meta
        $response['meta'] = array_filter($response['meta'], function ($value) {
            return $value !== null;
        });

        return response()->json($response, $status);
    }

    /**
     * Create a validation error response.
     */
    public static function validationError(
        array $errors,
        string $message = 'The provided data was invalid.',
        array $meta = []
    ): JsonResponse {
        return static::error(
            $message,
            $errors,
            422,
            'VALIDATION_FAILED',
            $meta
        );
    }

    /**
     * Create an unauthorized response.
     */
    public static function unauthorized(
        string $message = 'Unauthorized',
        array $meta = []
    ): JsonResponse {
        return static::error(
            $message,
            null,
            401,
            'UNAUTHORIZED',
            $meta
        );
    }

    /**
     * Create a forbidden response.
     */
    public static function forbidden(
        string $message = 'Forbidden',
        array $meta = []
    ): JsonResponse {
        return static::error(
            $message,
            null,
            403,
            'FORBIDDEN',
            $meta
        );
    }

    /**
     * Create a not found response.
     */
    public static function notFound(
        string $message = 'Not found',
        array $meta = []
    ): JsonResponse {
        return static::error(
            $message,
            null,
            404,
            'NOT_FOUND',
            $meta
        );
    }

    /**
     * Create a too many requests response.
     */
    public static function tooManyRequests(
        string $message = 'Too many requests',
        ?int $retryAfter = null,
        array $meta = []
    ): JsonResponse {
        $metaData = $meta;
        if ($retryAfter) {
            $metaData['retry_after_seconds'] = $retryAfter;
        }

        $response = static::error(
            $message,
            null,
            429,
            'TOO_MANY_REQUESTS',
            $metaData
        );

        if ($retryAfter) {
            $response->header('Retry-After', $retryAfter);
        }

        return $response;
    }

    /**
     * Create a server error response.
     */
    public static function serverError(
        string $message = 'Internal server error',
        array $meta = []
    ): JsonResponse {
        return static::error(
            $message,
            null,
            500,
            'SERVER_ERROR',
            $meta
        );
    }

    /**
     * Create a created response.
     */
    public static function created(
        string $message = 'Resource created successfully',
        mixed $data = null,
        array $meta = []
    ): JsonResponse {
        return static::success($message, $data, 201, $meta);
    }

    /**
     * Create an accepted response.
     */
    public static function accepted(
        string $message = 'Request accepted',
        mixed $data = null,
        array $meta = []
    ): JsonResponse {
        return static::success($message, $data, 202, $meta);
    }

    /**
     * Create a no content response.
     */
    public static function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * Create a paginated response.
     */
    public static function paginated(
        mixed $data,
        array $pagination,
        string $message = 'Data retrieved successfully',
        array $meta = []
    ): JsonResponse {
        $metaData = array_merge([
            'pagination' => $pagination,
        ], $meta);

        return static::success($message, $data, 200, $metaData);
    }

    /**
     * Create a response for resource collection.
     */
    public static function collection(
        mixed $data,
        ?int $total = null,
        string $message = 'Collection retrieved successfully',
        array $meta = []
    ): JsonResponse {
        $metaData = $meta;
        if ($total !== null) {
            $metaData['total_count'] = $total;
        }

        return static::success($message, $data, 200, $metaData);
    }
}
