# MiniMeet Backend - Multi-Tenant Meeting Operations Platform

**Purpose**: API meeting operations platform with extensible provider architecture for calendar and meeting artifact ingestion, analysis, and insights generation. Helps organizations optimize meeting efficiency and reduce costs by analyzing calendar data, transcriptions, duration, members, and generating AI-powered recommendations.

## Core Multi-Tenancy Architecture

This application uses **stancl/tenancy** package for strict multi-tenancy separation. Every feature, controller, service, and test must consider tenant context.

### Central vs Tenant Context - CRITICAL DISTINCTION

**Central Context** (Global Application Management):
- Tenant management and onboarding
- Domain management 
- Billing and subscriptions
- Authentication and user management
- Provider integrations setup (calendar connections)
- System-wide analytics and monitoring

**Tenant Context** (Organization-Specific Operations):
- Meeting data ingestion and processing
- Calendar analysis and insights
- Meeting recommendations and reports
- Organization users and roles
- Tenant-specific configuration
- Meeting artifacts (transcriptions, summaries)

## Docker-First Development

**ALL commands MUST run inside Docker containers. Dependencies are NOT installed on host.**

### Essential Docker Commands

```bash
# Application container access
docker compose exec app bash

# Central database operations
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed

# Tenant database operations
docker compose exec app php artisan tenants:migrate
docker compose exec app php artisan tenants:seed

# Testing
docker compose exec app php artisan test tests/Feature/Central
docker compose exec app php artisan test tests/Feature/Tenant

# Code formatting
docker compose exec app vendor/bin/pint --dirty

# Queue processing
docker compose exec app php artisan queue:work

# Artisan commands
docker compose exec app php artisan make:controller Central/TenantController
docker compose exec app php artisan make:controller Tenant/MeetingController
```

### Available Services (Docker Compose)

- **MySQL**: Central + tenant databases (port 3306)
- **MinIO**: S3-compatible object storage for meeting artifacts (port 9000/9001)
- **Redis**: Cache + queues for processing jobs (port 6379)
- **Mailpit**: Email testing (port 8025)
- **Nginx**: Web server (port 80)
- **App**: Laravel PHP application (port 8080)

## Directory Structure & Separation

### Controllers Architecture
```
app/Http/Controllers/
├── Controller.php                    # Base controller
├── Central/                          # Central context controllers
│   ├── TenantController.php         # Tenant CRUD operations
│   ├── DomainController.php         # Domain management
│   ├── AuthController.php           # Central authentication
│   └── ProviderController.php       # Calendar provider setup
└── Tenant/                          # Tenant context controllers
    ├── MeetingController.php        # Meeting data operations
    ├── CalendarController.php       # Calendar analysis
    ├── InsightController.php        # AI insights generation
    ├── ReportController.php         # Meeting reports
    └── UserController.php           # Tenant user management
```

### Services Architecture
```
app/Services/
├── Central/                         # Central business logic
│   ├── TenantService.php           # Tenant provisioning
│   ├── BillingService.php          # Subscription management
│   └── ProviderService.php         # Calendar provider integration
└── Tenant/                         # Tenant business logic
    ├── MeetingIngestionService.php  # Meeting data processing
    ├── CalendarAnalysisService.php  # Meeting analysis
    ├── InsightService.php           # AI-powered recommendations
    └── ReportService.php            # Report generation
```

### Actions Architecture (Single Responsibility)
```
app/Actions/
├── Central/
│   ├── CreateTenantAction.php
│   ├── SetupProviderAction.php
│   └── ProcessBillingAction.php
└── Tenant/
    ├── IngestMeetingDataAction.php
    ├── AnalyzeMeetingEfficiencyAction.php
    ├── GenerateInsightsAction.php
    └── CreateReportAction.php
```

### Repositories Architecture
```
app/Repositories/
├── Central/
│   ├── TenantRepository.php
│   ├── DomainRepository.php
│   └── ProviderRepository.php
└── Tenant/
    ├── MeetingRepository.php
    ├── CalendarRepository.php
    ├── InsightRepository.php
    └── UserRepository.php
```

### Models Architecture
```
app/Models/
├── Tenant.php                      # Central model
├── Domain.php                      # Central model
├── User.php                        # Shared model (context-aware)
├── Central/
│   ├── Provider.php               # Calendar providers
│   └── Subscription.php           # Billing
└── Tenant/                        # Tenant-specific models
    ├── Meeting.php
    ├── Calendar.php
    ├── Insight.php
    ├── Report.php
    └── MeetingParticipant.php
```

## Routes Architecture

### Central Routes (`routes/web.php`)
```php
<?php
use Illuminate\Support\Facades\Route;

// Central domain routes only
foreach (config('tenancy.central_domains') as $domain) {
    Route::domain($domain)->group(function () {
        Route::prefix('api')->group(function () {
            // Tenant management
            Route::apiResource('tenants', Central\TenantController::class);
            
            // Provider management
            Route::apiResource('providers', Central\ProviderController::class);
            
            // Authentication
            Route::post('auth/login', [Central\AuthController::class, 'login']);
        });
    });
}
```

### Tenant Routes (`routes/tenant.php`)
```php
<?php
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'api',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    // Meeting operations
    Route::apiResource('meetings', Tenant\MeetingController::class);
    
    // Calendar analysis
    Route::get('calendar/analysis', [Tenant\CalendarController::class, 'analysis']);
    
    // AI insights
    Route::get('insights', [Tenant\InsightController::class, 'index']);
    Route::post('insights/generate', [Tenant\InsightController::class, 'generate']);
    
    // Reports
    Route::get('reports', [Tenant\ReportController::class, 'index']);
    Route::post('reports/meeting-efficiency', [Tenant\ReportController::class, 'meetingEfficiency']);
});
```

## Testing Architecture - NEVER USE RefreshDatabase

### Central Tests (`tests/Feature/Central/`)
```php
<?php
namespace Tests\Feature\Central;

use Tests\TestCase;
// NEVER: use Illuminate\Foundation\Testing\RefreshDatabase;

class TenantManagementTest extends TestCase
{
    public function test_can_create_tenant(): void
    {
        // Test central tenant creation
        $response = $this->postJson('/api/tenants', [
            'name' => 'Acme Corp',
            'domain' => 'acme.minimeet.app'
        ]);
        
        $response->assertStatus(201);
    }
}
```

### Tenant Tests (`tests/Feature/Tenant/`)
```php
<?php
namespace Tests\Feature\Tenant;

use Tests\TestCase;
// NEVER: use Illuminate\Foundation\Testing\RefreshDatabase;

class MeetingAnalysisTest extends TestCase
{
    protected $tenancy = true; // Enable automatic tenancy for this test
    
    public function test_can_analyze_meeting_efficiency(): void
    {
        // Test within tenant context (automatically initialized by $tenancy = true)
        $response = $this->postJson('/api/meetings', [
            'title' => 'Weekly Standup',
            'duration' => 30,
            'participants' => 5
        ]);
        
        $response->assertStatus(201);
    }
    
    public function test_tenant_route_accessibility(): void
    {
        // Test tenant routes are accessible
        $response = $this->get('/');
        
        $response->assertStatus(200);
    }
}
```

## Database Migrations

### Central Migrations (`database/migrations/`)
- Tenant and domain management
- Provider configurations
- Central user authentication
- Billing and subscriptions

### Tenant Migrations (`database/migrations/tenant/`)
- Meeting data structure
- Calendar integration data
- AI insights storage
- Reports and analytics
- Organization users

```bash
# Create central migration
docker compose exec app php artisan make:migration create_providers_table

# Create tenant migration  
docker compose exec app php artisan make:migration create_meetings_table --path=database/migrations/tenant
```

## Queue Jobs for Meeting Processing

### Central Jobs
- Tenant provisioning
- Provider authentication
- Billing processing

### Tenant Jobs (Context-Aware)
- Meeting data ingestion
- AI analysis processing
- Report generation
- Calendar synchronization

```php
// Tenant-aware job
class ProcessMeetingDataJob implements ShouldQueue
{
    use InteractsWithTenancy;
    
    public function handle(): void
    {
        // Job automatically runs in correct tenant context
    }
}
```

## Laravel Boost Integration

### Artisan Commands
Always check available commands for multi-tenant operations:
```bash
docker compose exec app php artisan list
```

### Tinker for Debugging
```bash
# Central context debugging
docker compose exec app php artisan tinker
> \Stancl\Tenancy\Database\Models\Tenant::all()

# Tenant context debugging  
docker compose exec app php artisan tinker
> tenancy()->initialize(\Stancl\Tenancy\Database\Models\Tenant::first())
> App\Models\Tenant\Meeting::count()
```

### Documentation Search
Use `search-docs` tool for multi-tenancy specific Laravel documentation, focusing on:
- Tenancy patterns
- Queue processing in multi-tenant apps
- Database connections
- Testing strategies

## Development Workflow

1. **Feature Planning**: Determine if feature is Central or Tenant context
2. **Structure Creation**: Create controllers, services, actions in appropriate directory
3. **Route Definition**: Add to correct route file (web.php vs tenant.php)
4. **Migration Creation**: Create in appropriate migration directory
5. **Testing**: Write tests in correct context directory
6. **Queue Jobs**: Ensure tenant context is preserved
7. **Code Formatting**: `docker compose exec app vendor/bin/pint --dirty`

## Key Principles

- **Docker First**: Never run commands on host
- **Context Awareness**: Always know if you're in Central or Tenant context
- **Clean Separation**: Controllers, services, models separated by context
- **No RefreshDatabase**: Use proper test setup/teardown
- **Queue Context**: Jobs must preserve tenant context
- **Provider Architecture**: Extensible calendar integration system
- **AI Integration**: Built for meeting analysis and insights
- **Storage**: MinIO for meeting artifacts (transcriptions, recordings)

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines for Multi-Tenant Meeting Platform

The Laravel Boost guidelines are specifically curated for this multi-tenant meeting operations platform. All guidelines must consider tenant context separation.

## Foundational Context
This application is a Laravel application with multi-tenancy as the core architectural pattern:

- php - 8.3.24
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/pint (PINT) - v1
- stancl/tenancy - Multi-tenancy package

## Conventions
- **Multi-tenant first**: Every file creation must consider Central vs Tenant context
- Use descriptive names that indicate context: `TenantMeetingController` vs `CentralTenantController`
- Check existing Central/ and Tenant/ directories for patterns
- Never mix Central and Tenant logic in same class

## Verification Scripts
- Tests prove functionality - separate Central and Tenant test suites
- Use proper tenancy initialization in tests, not RefreshDatabase

## Application Structure & Architecture
- Maintain strict Central/Tenant directory separation
- Docker containers for all operations
- No changes to dependencies without approval

=== boost rules ===

## Laravel Boost for Multi-Tenancy
- All Laravel Boost tools work within tenant context
- Use `search-docs` for tenancy-specific documentation
- `tinker` tool respects current tenant context

## Artisan Commands
- Always run inside Docker: `docker compose exec app php artisan`
- Use `list-artisan-commands` to check tenant-specific commands
- Central commands: `php artisan migrate`
- Tenant commands: `php artisan tenants:migrate`

## URLs & Multi-Tenancy
- Central URLs: Use configured central domains
- Tenant URLs: Use tenant-specific domains/subdomains
- Use `get-absolute-url` tool for correct tenant URL generation

## Database Operations
- `database-query` tool respects tenant context
- `tinker` tool for debugging within tenant context
- Always specify connection for cross-tenant queries

## Documentation Search
- Search for multi-tenancy specific patterns
- Include queries like: `['multi tenant', 'tenancy', 'tenant context', 'stancl tenancy']`
- Filter packages for tenancy-related documentation

=== php rules ===

## PHP in Multi-Tenant Context

- Explicit return types mandatory
- Constructor property promotion
- Type hints for tenant/central context clarity

```php
// Clear context indication in method signatures
protected function createTenantMeeting(Tenant $tenant, array $meetingData): Meeting
{
    // Tenant context method
}

protected function provisionCentralTenant(string $domain): Tenant  
{
    // Central context method
}
```

## Comments & Documentation
- PHPDoc blocks must indicate Central vs Tenant context
- Document tenant context requirements

```php
/**
 * Process meeting data within tenant context
 * 
 * @param array{title: string, duration: int, participants: int} $meetingData
 * @return Meeting Tenant-specific meeting model
 * @throws TenantNotInitializedException
 */
```

=== laravel/core rules ===

## Multi-Tenant Laravel Patterns

### Artisan Commands
- `docker compose exec app php artisan make:controller Central/ProviderController`
- `docker compose exec app php artisan make:controller Tenant/MeetingController`
- Specify path for tenant migrations: `--path=database/migrations/tenant`

### Database & Models
- Central models: Direct connection to central database
- Tenant models: Automatic tenant database connection
- Use `CentralConnection` trait when accessing central data from tenant context

### API Resources
- Separate Central and Tenant API resources
- Version APIs per context: `/api/v1/central/` vs tenant routes

### Form Requests
- Context-specific validation rules
- Central requests: `app/Http/Requests/Central/`
- Tenant requests: `app/Http/Requests/Tenant/`

### Queues in Multi-Tenant Context
- Jobs must use `InteractsWithTenancy` trait
- Queue workers preserve tenant context
- Separate queue names for Central vs Tenant jobs

### Authentication
- Central authentication for tenant management
- Tenant-specific authentication for organization users
- Use Sanctum tokens with tenant context

### Testing Multi-Tenant Applications
- Central tests: Test tenant provisioning, billing, provider setup
- Tenant tests: Initialize tenancy in setUp(), end in tearDown()
- NEVER use RefreshDatabase - use proper tenant context management

```php
// Central test pattern
class CentralProviderTest extends TestCase
{
    public function test_can_setup_calendar_provider(): void
    {
        // Test central functionality
    }
}

// Tenant test pattern  
class TenantMeetingTest extends TestCase
{
    protected $tenancy = true; // Automatic tenancy initialization
    
    public function test_tenant_functionality(): void
    {
        // Tenancy is automatically handled by the framework
        // No manual setUp/tearDown needed
    }
}
```

=== laravel/v12 rules ===

## Laravel 12 Multi-Tenant Structure

### Directory Structure
- No middleware files in `app/Http/Middleware/` 
- Register tenancy middleware in `bootstrap/app.php`
- Tenant-specific providers in `bootstrap/providers.php`
- Commands auto-register from `app/Console/Commands/`

### Database Connections
- Central connection: Default Laravel database
- Tenant connections: Dynamic per-tenant databases
- Migration path specification required for tenant migrations

### Model Patterns
- Use `casts()` method over `$casts` property
- Tenant-aware model relationships
- Central models extend base, Tenant models use tenant connection

```php
// Central model
class Provider extends Model
{
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_active' => 'boolean',
        ];
    }
}

// Tenant model
class Meeting extends Model  
{
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
```

=== pint/core rules ===

## Code Formatting in Multi-Tenant Context

- Always run inside Docker: `docker compose exec app vendor/bin/pint --dirty`
- Format before commits to maintain consistency across Central/Tenant code
- Pint respects tenant context during formatting

=== meeting platform specific rules ===

## Meeting Operations Platform Guidelines

### Provider Architecture
- Extensible calendar provider system (Google, Outlook, etc.)
- Central provider configuration, tenant-specific connections
- OAuth flows handled in Central context, tokens stored per tenant

### Meeting Data Pipeline
1. **Ingestion**: Calendar APIs → Raw meeting data (Tenant context)
2. **Processing**: AI analysis of duration, participants, efficiency (Queue jobs)
3. **Storage**: MinIO for artifacts, MySQL for metadata
4. **Insights**: AI-generated recommendations and reports

### AI Integration
- Queue jobs for async AI processing
- Tenant-specific AI models and training data
- Redis caching for frequently accessed insights

### Calendar Integration
- Provider-agnostic abstraction layer
- Real-time synchronization with tenant calendars
- Meeting transcription and summary ingestion

### Reporting System
- Tenant-specific dashboards and KPIs
- Meeting efficiency metrics and recommendations
- Cost analysis and time-saving insights

</laravel-boost-guidelines>