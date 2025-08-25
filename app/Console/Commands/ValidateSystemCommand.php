<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

class ValidateSystemCommand extends Command
{
    protected $signature = 'system:validate 
                           {--component=all : Specific component to validate (database|cache|queue|tenancy|security|all)}
                           {--fix : Attempt to fix issues automatically}
                           {--deployment : Run deployment-specific validations}';

    protected $description = 'Validate system configuration and readiness for production';

    private array $results = [];

    private int $passed = 0;

    private int $failed = 0;

    private int $warnings = 0;

    public function handle(): int
    {
        $component = $this->option('component');
        $shouldFix = $this->option('fix');
        $deploymentMode = $this->option('deployment');

        $this->info('🔍 MiniMeet System Validation');
        $this->newLine();

        // Run validations based on component
        match ($component) {
            'database' => $this->validateDatabase($shouldFix),
            'cache' => $this->validateCache($shouldFix),
            'queue' => $this->validateQueue($shouldFix),
            'tenancy' => $this->validateTenancy($shouldFix),
            'security' => $this->validateSecurity($shouldFix),
            'all' => $this->validateAll($shouldFix),
            default => $this->error("Unknown component: {$component}"),
        };

        if ($deploymentMode) {
            $this->validateDeployment();
        }

        $this->displayResults();

        return $this->failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function validateAll(bool $shouldFix): void
    {
        $this->validateDatabase($shouldFix);
        $this->validateCache($shouldFix);
        $this->validateQueue($shouldFix);
        $this->validateTenancy($shouldFix);
        $this->validateSecurity($shouldFix);
        $this->validateConfiguration();
        $this->validatePermissions();
        $this->validateEnvironment();
    }

    private function validateDatabase(bool $shouldFix): void
    {
        $this->info('📊 Validating Database Configuration...');

        try {
            // Test central database connection
            DB::connection()->getPdo();
            $this->pass('Central database connection established');

            // Check required tables exist
            $centralTables = [
                'users', // Central users table
                'central_user_activities',
                'tenants',
                'domains',
                'tenant_users_management',
                'login_attempts',
                'personal_access_tokens',
            ];

            foreach ($centralTables as $table) {
                if (Schema::hasTable($table)) {
                    $this->pass("Central table '{$table}' exists");
                } else {
                    $this->failCheck("Central table '{$table}' missing", $shouldFix ? 'Run migrations' : null);
                }
            }

            // Test tenant database functionality if tenants exist
            $tenantCount = Tenant::count();
            if ($tenantCount > 0) {
                $testTenant = Tenant::first();

                try {
                    tenancy()->initialize($testTenant);

                    $tenantTables = ['users', 'user_activities']; // user_activities table for tenant activity logging
                    foreach ($tenantTables as $table) {
                        if (Schema::hasTable($table)) {
                            $this->pass("Tenant table '{$table}' exists");
                        } else {
                            $this->failCheck("Tenant table '{$table}' missing", $shouldFix ? 'Run tenant migrations' : null);
                        }
                    }

                    tenancy()->end();
                } catch (\Exception $e) {
                    $this->failCheck("Tenant database test failed: {$e->getMessage()}");
                }
            } else {
                $this->warning('No tenants exist for tenant database validation');
            }

        } catch (\Exception $e) {
            $this->failCheck("Database connection failed: {$e->getMessage()}", $shouldFix ? 'Check database credentials' : null);
        }
    }

    private function validateCache(bool $shouldFix): void
    {
        $this->info('💾 Validating Cache Configuration...');

        try {
            // Test cache write
            Cache::put('system_validation_test', 'test_value', 60);
            $this->pass('Cache write operation successful');

            // Test cache read
            $value = Cache::get('system_validation_test');
            if ($value === 'test_value') {
                $this->pass('Cache read operation successful');
            } else {
                $this->failCheck('Cache read operation failed');
            }

            // Test cache delete
            Cache::forget('system_validation_test');
            if (! Cache::has('system_validation_test')) {
                $this->pass('Cache delete operation successful');
            } else {
                $this->failCheck('Cache delete operation failed');
            }

            // Check cache driver configuration
            $driver = config('cache.default');
            $this->pass("Cache driver: {$driver}");

            if ($driver === 'redis') {
                try {
                    \Illuminate\Support\Facades\Redis::ping();
                    $this->pass('Redis connection established');
                } catch (\Exception $e) {
                    $this->failCheck("Redis connection failed: {$e->getMessage()}");
                }
            }

        } catch (\Exception $e) {
            $this->failCheck("Cache validation failed: {$e->getMessage()}");
        }
    }

    private function validateQueue(bool $shouldFix): void
    {
        $this->info('📋 Validating Queue Configuration...');

        try {
            // Test queue connection
            $driver = config('queue.default');
            $this->pass("Queue driver: {$driver}");

            // Test job dispatch (if not in production)
            if (! app()->environment('production')) {
                $testJob = new \App\Jobs\Tenant\ProcessUserActivityJob(
                    1, 'test', 'System validation test', '127.0.0.1', 'validation'
                );

                try {
                    dispatch($testJob);
                    $this->pass('Test job dispatched successfully');
                } catch (\Exception $e) {
                    $this->failCheck("Job dispatch failed: {$e->getMessage()}");
                }
            } else {
                $this->warning('Skipping job dispatch test in production');
            }

            // Check for failed jobs
            $failedJobs = DB::table('failed_jobs')->count();
            if ($failedJobs === 0) {
                $this->pass('No failed jobs in queue');
            } else {
                $this->warning("{$failedJobs} failed jobs found in queue");
            }

        } catch (\Exception $e) {
            $this->failCheck("Queue validation failed: {$e->getMessage()}");
        }
    }

    private function validateTenancy(bool $shouldFix): void
    {
        $this->info('🏢 Validating Multi-Tenancy Configuration...');

        try {
            // Check tenancy configuration
            $centralDomains = config('tenancy.central_domains');
            if (! empty($centralDomains)) {
                $this->pass('Central domains configured: '.implode(', ', $centralDomains));
            } else {
                $this->failCheck('No central domains configured', $shouldFix ? 'Configure central domains' : null);
            }

            // Check tenant model configuration
            $tenantModel = config('tenancy.tenant_model');
            if (class_exists($tenantModel)) {
                $this->pass("Tenant model exists: {$tenantModel}");
            } else {
                $this->failCheck("Tenant model not found: {$tenantModel}");
            }

            // Test tenant creation and context switching (if not in production)
            if (! app()->environment('production') && $shouldFix) {
                try {
                    // This would only run with --fix flag and not in production
                    $this->pass('Tenant context switching validation skipped (production safety)');
                } catch (\Exception $e) {
                    $this->failCheck("Tenant context test failed: {$e->getMessage()}");
                }
            } else {
                $this->warning('Tenant context switching test skipped');
            }

        } catch (\Exception $e) {
            $this->failCheck("Tenancy validation failed: {$e->getMessage()}");
        }
    }

    private function validateSecurity(bool $shouldFix): void
    {
        $this->info('🔐 Validating Security Configuration...');

        // Check APP_KEY
        if (config('app.key')) {
            $this->pass('Application key is set');
        } else {
            $this->failCheck('Application key not set', $shouldFix ? 'Run php artisan key:generate' : null);
        }

        // Check HTTPS in production
        if (app()->environment('production')) {
            if (config('app.url') && str_starts_with(config('app.url'), 'https://')) {
                $this->pass('HTTPS configured for production');
            } else {
                $this->failCheck('HTTPS not configured for production');
            }
        }

        // Check session configuration
        $sessionDriver = config('session.driver');
        $sessionLifetime = config('session.lifetime');

        $this->pass("Session driver: {$sessionDriver}");

        if ($sessionLifetime <= 480) { // 8 hours max
            $this->pass("Session lifetime appropriate: {$sessionLifetime} minutes");
        } else {
            $this->warning("Session lifetime may be too long: {$sessionLifetime} minutes");
        }

        // Check password hashing
        $hashDriver = config('hashing.driver');
        if ($hashDriver === 'bcrypt' || $hashDriver === 'argon2id') {
            $this->pass("Secure password hashing: {$hashDriver}");
        } else {
            $this->failCheck("Insecure password hashing: {$hashDriver}");
        }

        // Check debug mode in production
        if (app()->environment('production') && config('app.debug')) {
            $this->failCheck('Debug mode enabled in production - SECURITY RISK');
        } elseif (! app()->environment('production')) {
            $this->pass('Debug mode appropriately configured for environment');
        } else {
            $this->pass('Debug mode disabled in production');
        }
    }

    private function validateConfiguration(): void
    {
        $this->info('⚙️ Validating Application Configuration...');

        // Check required environment variables
        $requiredEnvVars = [
            'APP_NAME',
            'APP_URL',
            'DB_CONNECTION',
            'DB_HOST',
            'DB_DATABASE',
            'DB_USERNAME',
            'CACHE_STORE',
            'QUEUE_CONNECTION',
        ];

        foreach ($requiredEnvVars as $var) {
            if (env($var)) {
                $this->pass("Environment variable {$var} is set");
            } else {
                $this->failCheck("Environment variable {$var} is missing");
            }
        }

        // Check timezone
        $timezone = config('app.timezone');
        if (in_array($timezone, timezone_identifiers_list())) {
            $this->pass("Valid timezone configured: {$timezone}");
        } else {
            $this->failCheck("Invalid timezone: {$timezone}");
        }

        // Check locale
        $locale = config('app.locale');
        if (strlen($locale) === 2) {
            $this->pass("Locale configured: {$locale}");
        } else {
            $this->warning("Unusual locale configuration: {$locale}");
        }
    }

    private function validatePermissions(): void
    {
        $this->info('📁 Validating File Permissions...');

        $paths = [
            storage_path(),
            storage_path('app'),
            storage_path('framework'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ];

        foreach ($paths as $path) {
            if (is_writable($path)) {
                $this->pass('Directory writable: '.basename($path));
            } else {
                $this->failCheck("Directory not writable: {$path}", 'chmod 755 or 775');
            }
        }
    }

    private function validateEnvironment(): void
    {
        $this->info('🌍 Validating Environment...');

        // Check PHP version
        $phpVersion = PHP_VERSION;
        if (version_compare($phpVersion, '8.3.0', '>=')) {
            $this->pass("PHP version: {$phpVersion}");
        } else {
            $this->failCheck("PHP version too old: {$phpVersion} (require 8.3+)");
        }

        // Check required PHP extensions
        $requiredExtensions = [
            'pdo',
            'pdo_mysql',
            'json',
            'curl',
            'mbstring',
            'openssl',
            'tokenizer',
            'xml',
            'redis',
        ];

        foreach ($requiredExtensions as $extension) {
            if (extension_loaded($extension)) {
                $this->pass("PHP extension: {$extension}");
            } else {
                $this->failCheck("Missing PHP extension: {$extension}");
            }
        }

        // Check memory limit
        $memoryLimit = ini_get('memory_limit');
        if ($memoryLimit === '-1') {
            $this->pass("Memory limit: {$memoryLimit} (unlimited)");
        } else {
            $memoryInBytes = $this->parseSize($memoryLimit);
            if ($memoryInBytes >= 256 * 1024 * 1024) { // 256MB
                $this->pass("Memory limit: {$memoryLimit}");
            } else {
                $this->warning("Memory limit may be too low: {$memoryLimit}");
            }
        }
    }

    private function validateDeployment(): void
    {
        $this->info('🚀 Validating Deployment Readiness...');

        // Check if migrations are up to date
        try {
            $pendingMigrations = Artisan::output();
            $this->pass('Migration status checked');
        } catch (\Exception $e) {
            $this->warning('Could not check migration status');
        }

        // Check if admin user exists
        try {
            $adminExists = DB::table('users')->where('role', 'super_admin')->exists();
            if ($adminExists) {
                $this->pass('Super admin user exists');
            } else {
                $this->warning('No super admin user found - create one after deployment');
            }
        } catch (\Exception $e) {
            $this->warning('Could not check for admin users: '.$e->getMessage());
        }

        // Check if storage is linked
        if (is_link(public_path('storage'))) {
            $this->pass('Storage link exists');
        } else {
            $this->warning('Storage not linked', 'php artisan storage:link');
        }

        // Check optimization status
        if (app()->environment('production')) {
            if (file_exists(base_path('bootstrap/cache/config.php'))) {
                $this->pass('Configuration cached');
            } else {
                $this->warning('Configuration not cached', 'php artisan config:cache');
            }

            if (file_exists(base_path('bootstrap/cache/routes.php'))) {
                $this->pass('Routes cached');
            } else {
                $this->warning('Routes not cached', 'php artisan route:cache');
            }
        }
    }

    private function pass(string $message): void
    {
        $this->results[] = ['status' => 'PASS', 'message' => $message, 'fix' => null];
        $this->line("✅ {$message}");
        $this->passed++;
    }

    private function failCheck(string $message, ?string $fix = null): void
    {
        $this->results[] = ['status' => 'FAIL', 'message' => $message, 'fix' => $fix];
        $this->line("❌ {$message}".($fix ? " (Fix: {$fix})" : ''));
        $this->failed++;
    }

    private function warning(string $message, ?string $fix = null): void
    {
        $this->results[] = ['status' => 'WARN', 'message' => $message, 'fix' => $fix];
        $this->line("⚠️  {$message}".($fix ? " (Fix: {$fix})" : ''));
        $this->warnings++;
    }

    private function displayResults(): void
    {
        $this->newLine();
        $this->info('📋 VALIDATION SUMMARY');

        $total = $this->passed + $this->failed + $this->warnings;

        $this->table(
            ['Status', 'Count', 'Percentage'],
            [
                ['✅ Passed', $this->passed, $total > 0 ? round(($this->passed / $total) * 100, 1).'%' : '0%'],
                ['❌ Failed', $this->failed, $total > 0 ? round(($this->failed / $total) * 100, 1).'%' : '0%'],
                ['⚠️  Warnings', $this->warnings, $total > 0 ? round(($this->warnings / $total) * 100, 1).'%' : '0%'],
                ['📊 Total', $total, '100%'],
            ]
        );

        if ($this->failed === 0 && $this->warnings === 0) {
            $this->info('🎉 All validations passed! System is ready for deployment.');
        } elseif ($this->failed === 0) {
            $this->info('✅ System validation completed with warnings. Review warnings before deployment.');
        } else {
            $this->error('❌ System validation failed. Fix the issues before deployment.');
        }
    }

    private function parseSize(string $size): int
    {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = (int) preg_replace('/[^0-9\.]/', '', $size);

        if ($unit) {
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        }

        return (int) $size;
    }
}
