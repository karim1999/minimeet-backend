<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Central\CentralUserActivity;
use App\Models\Central\LoginAttempt;
use App\Services\Security\AuditLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SecurityMonitorCommand extends Command
{
    protected $signature = 'security:monitor 
                           {--hours=24 : Number of hours to analyze}
                           {--alert-threshold=10 : Number of events to trigger alert}
                           {--output=console : Output format (console|json|log)}';

    protected $description = 'Monitor security events and generate alerts';

    public function __construct(
        private readonly AuditLogService $auditLog
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $alertThreshold = (int) $this->option('alert-threshold');
        $outputFormat = $this->option('output');

        $this->info("Starting security monitoring for the last {$hours} hours...");

        $analysis = [
            'period' => [
                'hours' => $hours,
                'start_time' => now()->subHours($hours)->toISOString(),
                'end_time' => now()->toISOString(),
            ],
            'failed_logins' => $this->analyzeFailedLogins($hours),
            'suspicious_activities' => $this->analyzeSuspiciousActivities($hours),
            'rate_limit_violations' => $this->analyzeRateLimitViolations($hours),
            'security_violations' => $this->analyzeSecurityViolations($hours),
            'ip_analysis' => $this->analyzeIPAddresses($hours),
            'user_agent_analysis' => $this->analyzeUserAgents($hours),
            'recommendations' => [],
        ];

        // Generate recommendations based on analysis
        $analysis['recommendations'] = $this->generateRecommendations($analysis);

        // Check if alerts should be triggered
        $alertsTriggered = $this->checkAlertThresholds($analysis, $alertThreshold);

        // Output results
        $this->outputResults($analysis, $outputFormat, $alertsTriggered);

        // Store analysis results in cache for dashboard
        Cache::put('security_analysis_'.now()->format('Y-m-d-H'), $analysis, now()->addDays(7));

        return self::SUCCESS;
    }

    private function analyzeFailedLogins(int $hours): array
    {
        $failedLogins = LoginAttempt::where('successful', false)
            ->where('attempted_at', '>=', now()->subHours($hours))
            ->get();

        $byEmail = $failedLogins->groupBy('email')->map->count()->sortDesc();
        $byIP = $failedLogins->groupBy('ip_address')->map->count()->sortDesc();
        $byHour = $failedLogins->groupBy(fn ($attempt) => $attempt->attempted_at->format('Y-m-d H:00'))
            ->map->count()
            ->sortKeys();

        return [
            'total' => $failedLogins->count(),
            'unique_emails' => $byEmail->count(),
            'unique_ips' => $byIP->count(),
            'top_targeted_emails' => $byEmail->take(10)->toArray(),
            'top_attacking_ips' => $byIP->take(10)->toArray(),
            'hourly_distribution' => $byHour->toArray(),
            'peak_hour' => $byHour->keys()->first() ?? 'N/A',
        ];
    }

    private function analyzeSuspiciousActivities(int $hours): array
    {
        $suspiciousActivities = CentralUserActivity::where('action', 'suspicious_activity')
            ->where('created_at', '>=', now()->subHours($hours))
            ->get();

        $byType = $suspiciousActivities->map(fn ($activity) => $activity->metadata['context']['activity_type'] ?? 'unknown')
            ->countBy()
            ->sortDesc();

        $byIP = $suspiciousActivities->groupBy('ip_address')->map->count()->sortDesc();

        return [
            'total' => $suspiciousActivities->count(),
            'by_type' => $byType->toArray(),
            'by_ip' => $byIP->take(10)->toArray(),
            'unique_ips' => $byIP->count(),
        ];
    }

    private function analyzeRateLimitViolations(int $hours): array
    {
        $violations = CentralUserActivity::where('action', 'security_violation')
            ->where('description', 'LIKE', '%rate limit%')
            ->where('created_at', '>=', now()->subHours($hours))
            ->get();

        $byIP = $violations->groupBy('ip_address')->map->count()->sortDesc();
        $byType = $violations->map(fn ($violation) => $violation->metadata['context']['violation_type'] ?? 'unknown')
            ->countBy()
            ->sortDesc();

        return [
            'total' => $violations->count(),
            'by_ip' => $byIP->take(10)->toArray(),
            'by_type' => $byType->toArray(),
            'repeat_offenders' => $byIP->filter(fn ($count) => $count > 5)->toArray(),
        ];
    }

    private function analyzeSecurityViolations(int $hours): array
    {
        $violations = CentralUserActivity::where('action', 'security_violation')
            ->where('created_at', '>=', now()->subHours($hours))
            ->get();

        $byType = $violations->map(fn ($violation) => $violation->metadata['context']['violation_type'] ?? 'unknown')
            ->countBy()
            ->sortDesc();

        $bySeverity = $violations->map(fn ($violation) => $violation->metadata['severity'] ?? 'unknown')
            ->countBy()
            ->sortDesc();

        return [
            'total' => $violations->count(),
            'by_type' => $byType->toArray(),
            'by_severity' => $bySeverity->toArray(),
            'critical_count' => $violations->where('metadata.severity', 'critical')->count(),
        ];
    }

    private function analyzeIPAddresses(int $hours): array
    {
        $activities = CentralUserActivity::select('ip_address', 'action')
            ->where('created_at', '>=', now()->subHours($hours))
            ->whereIn('action', ['login_failed', 'security_violation', 'suspicious_activity'])
            ->get();

        $ipStats = $activities->groupBy('ip_address')->map(function ($ipActivities) {
            return [
                'total_events' => $ipActivities->count(),
                'event_types' => $ipActivities->groupBy('action')->map->count()->toArray(),
                'risk_score' => $this->calculateIPRiskScore($ipActivities),
            ];
        })->sortByDesc('risk_score');

        return [
            'total_unique_ips' => $ipStats->count(),
            'high_risk_ips' => $ipStats->filter(fn ($stats) => $stats['risk_score'] > 50)->take(10)->toArray(),
            'top_active_ips' => $ipStats->sortByDesc('total_events')->take(10)->toArray(),
        ];
    }

    private function analyzeUserAgents(int $hours): array
    {
        $userAgents = CentralUserActivity::select('user_agent')
            ->where('created_at', '>=', now()->subHours($hours))
            ->whereIn('action', ['login_failed', 'security_violation'])
            ->pluck('user_agent')
            ->filter()
            ->countBy()
            ->sortDesc();

        $suspiciousPatterns = ['bot', 'crawler', 'spider', 'curl', 'wget', 'python'];
        $suspiciousAgents = $userAgents->filter(function ($count, $userAgent) use ($suspiciousPatterns) {
            foreach ($suspiciousPatterns as $pattern) {
                if (stripos($userAgent, $pattern) !== false) {
                    return true;
                }
            }

            return false;
        });

        return [
            'total_unique_agents' => $userAgents->count(),
            'top_agents' => $userAgents->take(10)->toArray(),
            'suspicious_agents' => $suspiciousAgents->toArray(),
            'bot_activity_count' => $suspiciousAgents->sum(),
        ];
    }

    private function calculateIPRiskScore($activities): int
    {
        $score = 0;
        $eventCounts = $activities->countBy('action');

        // Add points based on different types of activities
        $score += ($eventCounts['login_failed'] ?? 0) * 2;
        $score += ($eventCounts['security_violation'] ?? 0) * 5;
        $score += ($eventCounts['suspicious_activity'] ?? 0) * 10;

        // Add bonus for high frequency
        if ($activities->count() > 20) {
            $score += 20;
        } elseif ($activities->count() > 10) {
            $score += 10;
        }

        return min($score, 100); // Cap at 100
    }

    private function generateRecommendations(array $analysis): array
    {
        $recommendations = [];

        // Failed login recommendations
        if ($analysis['failed_logins']['total'] > 100) {
            $recommendations[] = [
                'type' => 'high_failed_logins',
                'priority' => 'high',
                'message' => 'High number of failed login attempts detected. Consider implementing additional rate limiting.',
                'count' => $analysis['failed_logins']['total'],
            ];
        }

        // IP blocking recommendations
        foreach ($analysis['ip_analysis']['high_risk_ips'] as $ip => $stats) {
            if ($stats['risk_score'] > 80) {
                $recommendations[] = [
                    'type' => 'block_ip',
                    'priority' => 'critical',
                    'message' => "Consider blocking IP address {$ip} due to high risk score ({$stats['risk_score']})",
                    'ip_address' => $ip,
                    'risk_score' => $stats['risk_score'],
                ];
            }
        }

        // Suspicious activity recommendations
        if ($analysis['suspicious_activities']['total'] > 50) {
            $recommendations[] = [
                'type' => 'investigate_suspicious',
                'priority' => 'medium',
                'message' => 'High volume of suspicious activities. Manual investigation recommended.',
                'count' => $analysis['suspicious_activities']['total'],
            ];
        }

        // Bot activity recommendations
        if ($analysis['user_agent_analysis']['bot_activity_count'] > 20) {
            $recommendations[] = [
                'type' => 'bot_protection',
                'priority' => 'medium',
                'message' => 'Significant bot activity detected. Consider implementing CAPTCHA or bot protection.',
                'bot_requests' => $analysis['user_agent_analysis']['bot_activity_count'],
            ];
        }

        return $recommendations;
    }

    private function checkAlertThresholds(array $analysis, int $threshold): array
    {
        $alerts = [];

        if ($analysis['failed_logins']['total'] > $threshold) {
            $alerts[] = 'HIGH_FAILED_LOGINS';
        }

        if ($analysis['suspicious_activities']['total'] > $threshold / 2) {
            $alerts[] = 'HIGH_SUSPICIOUS_ACTIVITY';
        }

        if ($analysis['security_violations']['critical_count'] > 0) {
            $alerts[] = 'CRITICAL_SECURITY_VIOLATIONS';
        }

        if (count($analysis['ip_analysis']['high_risk_ips']) > 5) {
            $alerts[] = 'MULTIPLE_HIGH_RISK_IPS';
        }

        // Log alerts if any were triggered
        if (! empty($alerts)) {
            Log::channel('security')->critical('Security alerts triggered', [
                'alerts' => $alerts,
                'analysis_summary' => [
                    'failed_logins' => $analysis['failed_logins']['total'],
                    'suspicious_activities' => $analysis['suspicious_activities']['total'],
                    'security_violations' => $analysis['security_violations']['total'],
                    'high_risk_ips' => count($analysis['ip_analysis']['high_risk_ips']),
                ],
            ]);
        }

        return $alerts;
    }

    private function outputResults(array $analysis, string $format, array $alerts): void
    {
        switch ($format) {
            case 'json':
                $this->line(json_encode($analysis, JSON_PRETTY_PRINT));
                break;

            case 'log':
                Log::channel('security')->info('Security monitoring report', $analysis);
                $this->info('Security analysis logged to security channel');
                break;

            default:
                $this->displayConsoleOutput($analysis, $alerts);
        }
    }

    private function displayConsoleOutput(array $analysis, array $alerts): void
    {
        if (! empty($alerts)) {
            $this->error('🚨 SECURITY ALERTS TRIGGERED:');
            foreach ($alerts as $alert) {
                $this->error("   - {$alert}");
            }
            $this->newLine();
        }

        $this->info('📊 SECURITY ANALYSIS SUMMARY');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Failed Logins', $analysis['failed_logins']['total']],
                ['Suspicious Activities', $analysis['suspicious_activities']['total']],
                ['Rate Limit Violations', $analysis['rate_limit_violations']['total']],
                ['Security Violations', $analysis['security_violations']['total']],
                ['Unique Attacking IPs', $analysis['failed_logins']['unique_ips']],
                ['High Risk IPs', count($analysis['ip_analysis']['high_risk_ips'])],
                ['Bot Requests', $analysis['user_agent_analysis']['bot_activity_count']],
            ]
        );

        if (! empty($analysis['recommendations'])) {
            $this->newLine();
            $this->info('💡 RECOMMENDATIONS:');
            foreach ($analysis['recommendations'] as $rec) {
                $priority = strtoupper($rec['priority']);
                $this->line("   [{$priority}] {$rec['message']}");
            }
        }

        $this->newLine();
        $this->info('✅ Security monitoring completed successfully');
    }
}
