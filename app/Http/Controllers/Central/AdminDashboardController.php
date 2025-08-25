<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Traits\HandlesExceptions;
use App\Http\Responses\ApiResponse;
use App\Services\Central\AdminDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDashboardController extends ApiController
{
    use HandlesExceptions;

    public function __construct(
        private readonly AdminDashboardService $dashboardService
    ) {}

    /**
     * Display the admin dashboard.
     */
    public function index(): View
    {
        $stats = $this->dashboardService->getOverviewStats();
        $recentUsers = $this->dashboardService->getRecentUsers(5);
        $systemHealth = $this->dashboardService->getSystemHealth();

        return view('admin.dashboard', compact('stats', 'recentUsers', 'systemHealth'));
    }

    /**
     * Display system statistics page.
     */
    public function systemStats(): View|JsonResponse
    {
        $stats = $this->dashboardService->getOverviewStats();
        $tenantStats = $this->dashboardService->getTenantStats();
        $userStats = $this->dashboardService->getUserStats();
        $systemHealth = $this->dashboardService->getSystemHealth();

        // Return JSON for API requests
        if (request()->expectsJson()) {
            return ApiResponse::success(
                'System statistics retrieved successfully',
                [
                    'stats' => $stats,
                    'tenantStats' => $tenantStats,
                    'userStats' => $userStats,
                    'systemHealth' => $systemHealth,
                ]
            );
        }

        return view('admin.system-stats', compact('stats', 'tenantStats', 'userStats', 'systemHealth'));
    }

    /**
     * Get dashboard data for API (combines stats, recent users, and system health).
     */
    public function dashboard(Request $request): JsonResponse
    {
        try {
            // Cache dashboard data for 1 minute to improve performance and test consistency
            $cacheKey = 'admin_dashboard_data';
            $data = \Cache::remember($cacheKey, 60, function () {
                return [
                    'stats' => $this->dashboardService->getOverviewStats(),
                    'recentUsers' => $this->dashboardService->getRecentUsers(5),
                    'systemHealth' => $this->dashboardService->getSystemHealth(),
                ];
            });

            return ApiResponse::success(
                'Dashboard data retrieved successfully',
                $data
            );
        } catch (\Exception $e) {
            // For debugging - return the error in the response
            return response()->json([
                'success' => false,
                'message' => 'Dashboard error',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Get platform overview statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $stats = $this->dashboardService->getOverviewStats();

        return ApiResponse::success(
            'Platform statistics retrieved successfully',
            ['stats' => $stats]
        );
    }

    /**
     * Get tenant statistics.
     */
    public function tenantStats(Request $request): JsonResponse
    {
        $tenantStats = $this->dashboardService->getTenantStats();

        return ApiResponse::success(
            'Tenant statistics retrieved successfully',
            ['tenant_stats' => $tenantStats]
        );
    }

    /**
     * Get user statistics across all tenants.
     */
    public function userStats(Request $request): JsonResponse
    {
        $userStats = $this->dashboardService->getUserStats();

        return ApiResponse::success(
            'User statistics retrieved successfully',
            ['user_stats' => $userStats]
        );
    }

    /**
     * Get recent platform activities.
     */
    public function activities(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => ['integer', 'min:1', 'max:100'],
            'page' => ['integer', 'min:1'],
        ]);

        $limit = $request->input('limit', 50);
        $activities = $this->dashboardService->getRecentActivities($limit);

        return ApiResponse::success(
            'Recent activities retrieved successfully',
            ['activities' => $activities]
        );
    }

    /**
     * Get system health information.
     */
    public function health(Request $request): JsonResponse
    {
        $health = $this->dashboardService->getSystemHealth();

        return ApiResponse::success(
            'System health retrieved successfully',
            ['health' => $health]
        );
    }

    /**
     * Get real-time metrics for dashboard.
     */
    public function metrics(Request $request): JsonResponse
    {
        $request->validate([
            'timeframe' => ['string', 'in:1h,6h,24h,7d,30d'],
            'metric' => ['string', 'in:users,tenants,activities,logins'],
        ]);

        $timeframe = $request->input('timeframe', '24h');
        $metric = $request->input('metric', 'users');

        $metrics = $this->dashboardService->getMetrics($timeframe, $metric);

        return ApiResponse::success(
            'Metrics retrieved successfully',
            [
                'timeframe' => $timeframe,
                'metric' => $metric,
                'data' => $metrics,
            ]
        );
    }

    /**
     * Export dashboard data as CSV.
     *
     * @deprecated Use AdminExportController instead
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $request->validate([
            'type' => ['required', 'string', 'in:tenants,users,activities'],
            'format' => ['string', 'in:csv,xlsx'],
        ]);

        $type = $request->input('type');
        $format = $request->input('format', 'csv');

        return $this->dashboardService->exportData($type, $format);
    }
}
