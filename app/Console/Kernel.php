<?php

declare(strict_types=1);

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     */
    protected $commands = [
        Commands\ScheduleTenantStatsUpdate::class,
        Commands\SecurityMonitorCommand::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Update tenant statistics every hour
        $schedule->command('tenants:update-stats')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/tenant-stats.log'));

        // Security monitoring every 15 minutes
        $schedule->command('security:monitor --hours=1 --alert-threshold=10 --output=log')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        // Daily comprehensive security report
        $schedule->command('security:monitor --hours=24 --alert-threshold=50 --output=console')
            ->daily()
            ->at('08:00')
            ->emailOutputOnFailure('security@minimeet.app');

        // Clean up old activity logs (keep last 90 days)
        $schedule->command('model:prune', ['--model' => 'App\\Models\\Tenant\\TenantUserActivity'])
            ->daily()
            ->at('02:00')
            ->withoutOverlapping();

        // Clean up old central user activities
        $schedule->command('model:prune', ['--model' => 'App\\Models\\Central\\CentralUserActivity'])
            ->daily()
            ->at('02:30')
            ->withoutOverlapping();

        // Queue job cleanup
        $schedule->command('queue:prune-batches')
            ->daily()
            ->at('03:00');

        $schedule->command('queue:prune-failed', ['--hours' => 168]) // 7 days
            ->daily()
            ->at('03:30');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
