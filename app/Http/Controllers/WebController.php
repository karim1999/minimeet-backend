<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

abstract class WebController extends Controller
{
    /**
     * Return a view response.
     */
    protected function view(string $view, array $data = []): View
    {
        return view($view, $data);
    }

    /**
     * Return a redirect response.
     */
    protected function redirectTo(string $route, array $parameters = []): RedirectResponse
    {
        return redirect()->route($route, $parameters);
    }

    /**
     * Return a redirect back response with success message.
     */
    protected function redirectBackWithSuccess(string $message): RedirectResponse
    {
        return redirect()->back()->with('success', $message);
    }

    /**
     * Return a redirect back response with error message.
     */
    protected function redirectBackWithError(string $message): RedirectResponse
    {
        return redirect()->back()->with('error', $message)->withInput();
    }

    /**
     * Return a redirect with success message.
     */
    protected function redirectWithSuccess(string $route, string $message, array $parameters = []): RedirectResponse
    {
        return redirect()->route($route, $parameters)->with('success', $message);
    }

    /**
     * Return a redirect with error message.
     */
    protected function redirectWithError(string $route, string $message, array $parameters = []): RedirectResponse
    {
        return redirect()->route($route, $parameters)->with('error', $message);
    }

    /**
     * Return a redirect with validation errors.
     */
    protected function redirectWithValidationError(string $route, array $errors, array $parameters = []): RedirectResponse
    {
        return redirect()->route($route, $parameters)->withErrors($errors)->withInput();
    }

    /**
     * Get pagination parameters for web requests.
     */
    protected function getWebPaginationParams(Request $request): array
    {
        return [
            'page' => $request->input('page', 1),
            'per_page' => min(max((int) $request->input('per_page', 20), 10), 50),
        ];
    }

    /**
     * Get search parameters for web requests.
     */
    protected function getWebSearchParams(Request $request, array $searchableFields = []): array
    {
        $params = [];

        if ($search = $request->input('search')) {
            $params['search'] = $search;
        }

        foreach ($searchableFields as $field) {
            if ($value = $request->input($field)) {
                $params[$field] = $value;
            }
        }

        return $params;
    }

    /**
     * Check if request expects JSON response.
     */
    protected function expectsJson(Request $request): bool
    {
        return $request->expectsJson() || $request->is('api/*');
    }
}
