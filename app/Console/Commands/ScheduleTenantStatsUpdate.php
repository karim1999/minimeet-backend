<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\Central\UpdateTenantStatsJob;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ScheduleTenantStatsUpdate extends Command
{
    protected $signature = 'tenants:update-stats 
                           {--tenant= : Update stats for specific tenant ID} 
                           {--force : Force update even if recently updated}';

    protected $description = 'Update user and activity statistics for all tenants';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $force = $this->option('force');

        try {
            if ($tenantId) {
                $this->updateTenantStats($tenantId, $force);
            } else {
                $this->updateAllTenantStats($force);
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to schedule tenant stats update: {$e->getMessage()}");
            Log::error('Tenant stats update command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }

    private function updateTenantStats(string $tenantId, bool $force): void
    {
        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            $this->error("Tenant with ID {$tenantId} not found");

            return;
        }

        $this->info("Scheduling stats update for tenant: {$tenant->id}");

        if (! $force && $this->wasRecentlyUpdated($tenant)) {
            $this->warn("Tenant {$tenant->id} was recently updated. Use --force to override.");

            return;
        }

        UpdateTenantStatsJob::dispatch($tenant->id);
        $this->info("Stats update job queued for tenant: {$tenant->id}");
    }

    private function updateAllTenantStats(bool $force): void
    {
        $query = Tenant::query();

        if (! $force) {
            // Only update tenants that haven't been updated in the last hour
            $query->whereDoesntHave('userManagement', function ($q) {
                $q->where('updated_at', '>=', now()->subHour());
            })->orWhereDoesntHave('userManagement');
        }

        $tenants = $query->get();
        $totalTenants = $tenants->count();

        if ($totalTenants === 0) {
            $this->info('No tenants need stats updates.');

            return;
        }

        $this->info("Scheduling stats updates for {$totalTenants} tenant(s)");

        $progressBar = $this->output->createProgressBar($totalTenants);
        $progressBar->start();

        $queued = 0;

        foreach ($tenants as $tenant) {
            try {
                UpdateTenantStatsJob::dispatch($tenant->id);
                $queued++;
            } catch (\Exception $e) {
                $this->error("\nFailed to queue job for tenant {$tenant->id}: {$e->getMessage()}");
                Log::error('Failed to queue tenant stats job', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);
        $this->info("Successfully queued stats update jobs for {$queued}/{$totalTenants} tenants");

        if ($queued < $totalTenants) {
            $this->warn('Some jobs failed to queue. Check logs for details.');
        }
    }

    private function wasRecentlyUpdated(Tenant $tenant): bool
    {
        return $tenant->userManagement()
            ->where('updated_at', '>=', now()->subHour())
            ->exists();
    }
}
