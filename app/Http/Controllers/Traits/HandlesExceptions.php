<?php

namespace App\Http\Controllers\Traits;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait HandlesExceptions
{
    /**
     * Handle exceptions for API controllers.
     */
    protected function handleApiException(\Throwable $exception, string $context = ''): JsonResponse
    {
        // Log the exception
        $this->logException($exception, $context);

        return match (true) {
            $exception instanceof ValidationException => $this->respondValidationError(
                $exception->errors(),
                'Validation failed'
            ),
            $exception instanceof AuthenticationException => $this->respondBadRequest(
                $exception->getMessage() ?: 'Authentication failed'
            ),
            $exception instanceof ModelNotFoundException => $this->respondNotFound(
                'Resource not found'
            ),
            $exception instanceof HttpException => $this->respondWithError(
                $exception->getMessage() ?: 'HTTP Error',
                $exception->getStatusCode()
            ),
            default => $this->respondServerError('An unexpected error occurred')
        };
    }

    /**
     * Handle exceptions for web controllers.
     */
    protected function handleWebException(\Throwable $exception, Request $request, string $context = ''): RedirectResponse
    {
        // Log the exception
        $this->logException($exception, $context);

        return match (true) {
            $exception instanceof ValidationException => redirect()
                ->back()
                ->withErrors($exception->errors())
                ->withInput(),
            $exception instanceof ModelNotFoundException => redirect()
                ->back()
                ->with('error', 'Resource not found'),
            $exception instanceof HttpException => redirect()
                ->back()
                ->with('error', $exception->getMessage() ?: 'An error occurred'),
            default => redirect()
                ->back()
                ->with('error', 'An unexpected error occurred')
        };
    }

    /**
     * Log exception with context.
     */
    protected function logException(\Throwable $exception, string $context = ''): void
    {
        $logContext = [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ];

        if ($context) {
            $logContext['context'] = $context;
        }

        Log::error('Controller Exception: '.get_class($exception), $logContext);
    }

    /**
     * Execute a callable with exception handling for API.
     */
    protected function executeForApi(callable $callback, string $context = ''): JsonResponse
    {
        try {
            return $callback();
        } catch (\Throwable $exception) {
            return $this->handleApiException($exception, $context);
        }
    }

    /**
     * Execute a callable with exception handling for web.
     */
    protected function executeForWeb(callable $callback, Request $request, string $context = ''): mixed
    {
        try {
            return $callback();
        } catch (\Throwable $exception) {
            return $this->handleWebException($exception, $request, $context);
        }
    }
}
