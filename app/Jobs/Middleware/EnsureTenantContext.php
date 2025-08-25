<?php

declare(strict_types=1);

namespace App\Jobs\Middleware;

use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Queue\TenantAware;

class EnsureTenantContext
{
    public function handle(object $job, callable $next): mixed
    {
        if (! ($job instanceof TenantAware)) {
            // Job is not tenant-aware, proceed normally
            return $next($job);
        }

        // Get the tenant from the job
        $tenant = $job->tenant ?? null;

        if (! $tenant) {
            Log::warning('Tenant-aware job executed without tenant context', [
                'job_class' => get_class($job),
                'job_id' => method_exists($job, 'getJobId') ? $job->getJobId() : 'unknown',
            ]);

            // Proceed anyway - some jobs might handle missing tenant gracefully
            return $next($job);
        }

        try {
            // Initialize tenant context
            tenancy()->initialize($tenant);

            Log::debug('Tenant context initialized for job', [
                'job_class' => get_class($job),
                'tenant_id' => $tenant->getKey(),
                'tenant_key' => $tenant->getTenantKey(),
            ]);

            // Execute the job with tenant context
            $result = $next($job);

            Log::debug('Job executed successfully with tenant context', [
                'job_class' => get_class($job),
                'tenant_id' => $tenant->getKey(),
            ]);

            return $result;
        } catch (\Throwable $e) {
            Log::error('Job failed with tenant context', [
                'job_class' => get_class($job),
                'tenant_id' => $tenant->getKey(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        } finally {
            // Always clean up tenant context
            if (tenancy()->initialized) {
                tenancy()->end();

                Log::debug('Tenant context ended for job', [
                    'job_class' => get_class($job),
                    'tenant_id' => $tenant->getKey(),
                ]);
            }
        }
    }
}
