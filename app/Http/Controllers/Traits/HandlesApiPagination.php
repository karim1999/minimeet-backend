<?php

namespace App\Http\Controllers\Traits;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

trait HandlesApiPagination
{
    /**
     * Get pagination parameters from request.
     */
    protected function getPaginationParams(Request $request): array
    {
        return [
            'page' => $request->input('page', 1),
            'per_page' => $this->getPerPageLimit($request),
        ];
    }

    /**
     * Get per_page limit with validation.
     */
    protected function getPerPageLimit(Request $request): int
    {
        $perPage = (int) $request->input('per_page', 15);
        $maxPerPage = $this->getMaxPerPageLimit();
        $minPerPage = $this->getMinPerPageLimit();

        return min(max($perPage, $minPerPage), $maxPerPage);
    }

    /**
     * Get maximum per_page limit.
     */
    protected function getMaxPerPageLimit(): int
    {
        return 100;
    }

    /**
     * Get minimum per_page limit.
     */
    protected function getMinPerPageLimit(): int
    {
        return 5;
    }

    /**
     * Format paginated response for API.
     */
    protected function formatPaginatedResponse(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'has_more_pages' => $paginator->hasMorePages(),
            ],
        ];
    }

    /**
     * Validate pagination parameters.
     */
    protected function validatePaginationParams(Request $request): array
    {
        return $request->validate([
            'page' => ['integer', 'min:1'],
            'per_page' => ['integer', 'min:'.$this->getMinPerPageLimit(), 'max:'.$this->getMaxPerPageLimit()],
        ]);
    }

    /**
     * Get search parameters from request.
     */
    protected function getSearchParams(Request $request, array $searchableFields = []): array
    {
        $params = [];

        // General search term
        if ($search = $request->input('search')) {
            $params['search'] = $search;
        }

        // Specific field searches
        foreach ($searchableFields as $field) {
            if ($value = $request->input($field)) {
                $params[$field] = $value;
            }
        }

        return $params;
    }

    /**
     * Get sort parameters from request.
     */
    protected function getSortParams(Request $request, array $allowedSortFields = []): array
    {
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');

        // Validate sort field
        if (! empty($allowedSortFields) && ! in_array($sortBy, $allowedSortFields)) {
            $sortBy = $allowedSortFields[0] ?? 'created_at';
        }

        // Validate sort direction
        if (! in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'desc';
        }

        return [
            'sort_by' => $sortBy,
            'sort_direction' => $sortDirection,
        ];
    }
}
