<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Traits\HandlesExceptions;
use App\Services\Central\AdminDashboardService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminExportController extends ApiController
{
    use HandlesExceptions;

    public function __construct(
        private readonly AdminDashboardService $dashboardService
    ) {}

    /**
     * Export data in various formats.
     */
    public function export(Request $request): StreamedResponse
    {
        return $this->executeForApi(function () use ($request) {
            $request->validate([
                'type' => ['required', 'string', 'in:tenants,users,activities'],
                'format' => ['string', 'in:csv,xlsx'],
                'date_from' => ['nullable', 'date'],
                'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
                'tenant_id' => ['nullable', 'string', 'exists:tenants,id'],
            ]);

            $type = $request->input('type');
            $format = $request->input('format', 'csv');
            $filters = $request->only(['date_from', 'date_to', 'tenant_id']);

            return $this->dashboardService->exportData($type, $format, $filters);
        }, 'exporting data');
    }

    /**
     * Export tenant data.
     */
    public function exportTenants(Request $request): StreamedResponse
    {
        return $this->executeForApi(function () use ($request) {
            $request->validate([
                'format' => ['string', 'in:csv,xlsx'],
                'include_stats' => ['boolean'],
                'status' => ['nullable', 'string', 'in:active,inactive'],
            ]);

            $format = $request->input('format', 'csv');
            $options = $request->only(['include_stats', 'status']);

            return $this->dashboardService->exportTenants($format, $options);
        }, 'exporting tenants');
    }

    /**
     * Export user data.
     */
    public function exportUsers(Request $request): StreamedResponse
    {
        return $this->executeForApi(function () use ($request) {
            $request->validate([
                'format' => ['string', 'in:csv,xlsx'],
                'tenant_id' => ['nullable', 'string', 'exists:tenants,id'],
                'role' => ['nullable', 'string', 'in:owner,admin,manager,member'],
                'include_activity' => ['boolean'],
            ]);

            $format = $request->input('format', 'csv');
            $filters = $request->only(['tenant_id', 'role', 'include_activity']);

            return $this->dashboardService->exportUsers($format, $filters);
        }, 'exporting users');
    }

    /**
     * Export activity data.
     */
    public function exportActivities(Request $request): StreamedResponse
    {
        return $this->executeForApi(function () use ($request) {
            $request->validate([
                'format' => ['string', 'in:csv,xlsx'],
                'tenant_id' => ['nullable', 'string', 'exists:tenants,id'],
                'user_id' => ['nullable', 'integer'],
                'action' => ['nullable', 'string'],
                'date_from' => ['nullable', 'date'],
                'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            ]);

            $format = $request->input('format', 'csv');
            $filters = $request->only(['tenant_id', 'user_id', 'action', 'date_from', 'date_to']);

            return $this->dashboardService->exportActivities($format, $filters);
        }, 'exporting activities');
    }

    /**
     * Get export status.
     */
    public function exportStatus(Request $request, string $exportId)
    {
        return $this->executeForApi(function () use ($exportId) {
            $status = $this->dashboardService->getExportStatus($exportId);

            if (! $status) {
                return $this->respondNotFound('Export not found');
            }

            return $this->respondWithSuccess(
                $status,
                'Export status retrieved successfully'
            );
        }, 'retrieving export status');
    }

    /**
     * Download completed export.
     */
    public function downloadExport(Request $request, string $exportId): StreamedResponse
    {
        return $this->executeForApi(function () use ($exportId) {
            return $this->dashboardService->downloadExport($exportId);
        }, 'downloading export file');
    }
}
