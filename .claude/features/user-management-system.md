# User Management System - Multi-Tenant Feature Specification

## Feature Overview

### Purpose
Implement a comprehensive user management system for the MiniMeet multi-tenant platform that properly separates Central administrators from Tenant users, providing appropriate authentication, authorization, and management interfaces for each context.

### Scope
- Central admin web interface for system administrators
- Tenant user authentication and management system
- Role-based access control with proper context separation
- API-based authentication for tenant operations
- Web-based dashboard for central administration

### Business Value
- Enable system administrators to manage the entire platform through a centralized interface
- Allow tenants to independently manage their organization users
- Provide secure, context-aware authentication preventing cross-tenant access
- Create foundation for role-based features and permissions within tenants

## Architecture Analysis

### Current State Assessment
The codebase already has:
- **Multi-tenancy Setup**: Properly configured stancl/tenancy package
- **Central Authentication**: Working central auth system with token management
- **Database Structure**: Separate central and tenant databases
- **Route Separation**: Distinct routing files for central (api.php, web.php) and tenant (tenant.php) contexts
- **Service Architecture**: Central services for authentication, sessions, and tokens
- **Testing Framework**: Tenant-aware test setup with proper context initialization

### Integration Points
1. **User Model**: Currently shared between contexts, needs enhancement for role management
2. **Authentication Guards**: Need separate guards for central admins vs tenant users
3. **Middleware**: Requires new middleware for central admin web access
4. **Database**: Need migrations for roles, permissions, and tenant user metadata
5. **Frontend**: Need to integrate Tailwind CSS for admin interface

## Database Design

### Central Database Migrations

#### 1. Update Central Users Table
```sql
-- Migration: add_role_to_central_users_table
ALTER TABLE users ADD COLUMN role ENUM('super_admin', 'admin', 'support') DEFAULT 'admin' AFTER email;
ALTER TABLE users ADD COLUMN is_central BOOLEAN DEFAULT TRUE AFTER role;
ALTER TABLE users ADD COLUMN last_login_at TIMESTAMP NULL AFTER remember_token;
ALTER TABLE users ADD COLUMN metadata JSON NULL AFTER last_login_at;
```

#### 2. Create Central User Activities Table
```sql
-- Migration: create_central_user_activities_table
CREATE TABLE central_user_activities (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(255) NOT NULL,
    model_type VARCHAR(255) NULL,
    model_id VARCHAR(255) NULL,
    metadata JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_activities (user_id, created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

#### 3. Create Tenant User Management Table
```sql
-- Migration: create_tenant_users_view_table
CREATE TABLE tenant_users_management (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    tenant_id VARCHAR(255) NOT NULL,
    user_count INT DEFAULT 0,
    active_users INT DEFAULT 0,
    last_activity_at TIMESTAMP NULL,
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant_stats (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

### Tenant Database Migrations

#### 1. Create Tenant Users Table
```sql
-- Migration: database/migrations/tenant/create_tenant_users_table
CREATE TABLE users (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('owner', 'admin', 'manager', 'member') DEFAULT 'member',
    department VARCHAR(255) NULL,
    title VARCHAR(255) NULL,
    phone VARCHAR(50) NULL,
    avatar_url VARCHAR(500) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_login_at TIMESTAMP NULL,
    settings JSON NULL,
    metadata JSON NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    UNIQUE KEY unique_email (email),
    INDEX idx_active_users (is_active, deleted_at),
    INDEX idx_role (role)
);
```

#### 2. Create Tenant User Sessions Table
```sql
-- Migration: database/migrations/tenant/create_tenant_sessions_table
CREATE TABLE sessions (
    id VARCHAR(255) PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    payload LONGTEXT NOT NULL,
    last_activity INT NOT NULL,
    INDEX sessions_user_id_index (user_id),
    INDEX sessions_last_activity_index (last_activity),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

#### 3. Create Tenant User Activities Table
```sql
-- Migration: database/migrations/tenant/create_tenant_user_activities_table
CREATE TABLE user_activities (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(255) NOT NULL,
    description TEXT NULL,
    model_type VARCHAR(255) NULL,
    model_id BIGINT UNSIGNED NULL,
    metadata JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_activity (user_id, created_at),
    INDEX idx_action (action),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

## Models Architecture

### Central Models

#### 1. App\Models\Central\CentralUser
```php
namespace App\Models\Central;

class CentralUser extends User
{
    protected $connection = 'central';
    protected $table = 'users';
    
    // Relationships
    public function activities(): HasMany
    public function managedTenants(): HasMany
    
    // Scopes
    public function scopeAdmins($query)
    public function scopeActive($query)
    
    // Methods
    public function isSuperAdmin(): bool
    public function canManageTenant(Tenant $tenant): bool
    public function logActivity(string $action, $model = null): void
}
```

#### 2. App\Models\Central\CentralUserActivity
```php
namespace App\Models\Central;

class CentralUserActivity extends Model
{
    protected $connection = 'central';
    
    // Relationships
    public function user(): BelongsTo
    public function model(): MorphTo
    
    // Scopes
    public function scopeRecent($query)
    public function scopeByAction($query, string $action)
}
```

#### 3. App\Models\Central\TenantUserManagement
```php
namespace App\Models\Central;

class TenantUserManagement extends Model
{
    protected $connection = 'central';
    
    // Relationships
    public function tenant(): BelongsTo
    
    // Methods
    public function updateStats(): void
    public function getActiveUserCount(): int
}
```

### Tenant Models

#### 1. App\Models\Tenant\TenantUser
```php
namespace App\Models\Tenant;

class TenantUser extends Authenticatable
{
    use HasApiTokens, SoftDeletes;
    
    // Relationships
    public function activities(): HasMany
    public function meetings(): HasMany
    public function reports(): HasMany
    
    // Scopes
    public function scopeActive($query)
    public function scopeByRole($query, string $role)
    
    // Methods
    public function isOwner(): bool
    public function isAdmin(): bool
    public function canManageUsers(): bool
    public function logActivity(string $action, $model = null): void
}
```

#### 2. App\Models\Tenant\TenantUserActivity
```php
namespace App\Models\Tenant;

class TenantUserActivity extends Model
{
    // Relationships
    public function user(): BelongsTo
    public function model(): MorphTo
    
    // Scopes
    public function scopeToday($query)
    public function scopeByUser($query, int $userId)
}
```

## API Design

### Central Admin API Endpoints

#### Authentication Endpoints
- `POST /api/v1/admin/login` - Admin login
- `POST /api/v1/admin/logout` - Admin logout  
- `GET /api/v1/admin/user` - Get current admin user
- `POST /api/v1/admin/refresh` - Refresh authentication token

#### Tenant User Management Endpoints
- `GET /api/v1/admin/tenants/{tenant}/users` - List all users for a tenant
- `GET /api/v1/admin/tenants/{tenant}/users/{user}` - Get specific tenant user details
- `POST /api/v1/admin/tenants/{tenant}/users` - Create user in tenant
- `PUT /api/v1/admin/tenants/{tenant}/users/{user}` - Update tenant user
- `DELETE /api/v1/admin/tenants/{tenant}/users/{user}` - Delete tenant user
- `POST /api/v1/admin/tenants/{tenant}/users/{user}/suspend` - Suspend user
- `POST /api/v1/admin/tenants/{tenant}/users/{user}/activate` - Activate user
- `GET /api/v1/admin/tenants/{tenant}/users/{user}/activities` - Get user activities

#### Dashboard Statistics Endpoints
- `GET /api/v1/admin/stats/overview` - Platform overview statistics
- `GET /api/v1/admin/stats/tenants` - Tenant statistics
- `GET /api/v1/admin/stats/users` - User statistics across all tenants
- `GET /api/v1/admin/activities` - Recent platform activities

### Tenant API Endpoints

#### Authentication Endpoints
- `POST /api/auth/register` - Register new tenant user (if enabled)
- `POST /api/auth/login` - Tenant user login
- `POST /api/auth/logout` - Tenant user logout
- `GET /api/auth/user` - Get current authenticated user
- `POST /api/auth/refresh` - Refresh authentication token
- `POST /api/auth/password/forgot` - Request password reset
- `POST /api/auth/password/reset` - Reset password

#### User Management Endpoints (Admin/Owner only)
- `GET /api/users` - List tenant users
- `GET /api/users/{user}` - Get user details
- `POST /api/users` - Create new user
- `PUT /api/users/{user}` - Update user
- `DELETE /api/users/{user}` - Delete user
- `POST /api/users/{user}/suspend` - Suspend user
- `POST /api/users/{user}/activate` - Activate user

#### User Profile Endpoints
- `GET /api/profile` - Get current user profile
- `PUT /api/profile` - Update profile
- `POST /api/profile/password` - Change password
- `POST /api/profile/avatar` - Upload avatar

## Controllers Architecture

### Central Controllers

#### 1. App\Http\Controllers\Central\AdminAuthController
- `login()` - Handle admin login with 2FA support
- `logout()` - Invalidate admin session
- `user()` - Return authenticated admin details
- `refresh()` - Refresh authentication token

#### 2. App\Http\Controllers\Central\AdminDashboardController
- `index()` - Render admin dashboard view
- `stats()` - Get platform statistics
- `activities()` - Get recent activities

#### 3. App\Http\Controllers\Central\TenantUserManagementController
- `index()` - List users across tenants or for specific tenant
- `show()` - Get detailed user information
- `create()` - Create user in tenant context
- `update()` - Update tenant user
- `destroy()` - Remove user from tenant
- `suspend()` - Suspend tenant user
- `activate()` - Activate tenant user
- `activities()` - Get user activity log

#### 4. App\Http\Controllers\Central\TenantManagementController
- `index()` - List all tenants with user counts
- `show()` - Get tenant details with user statistics
- `users()` - Get paginated users for tenant
- `userStats()` - Get user statistics for tenant

### Tenant Controllers

#### 1. App\Http\Controllers\Tenant\AuthController
- `register()` - Register new user (if enabled)
- `login()` - Authenticate tenant user
- `logout()` - Invalidate session
- `user()` - Get current user
- `refresh()` - Refresh token

#### 2. App\Http\Controllers\Tenant\UserController
- `index()` - List tenant users
- `show()` - Get user details
- `store()` - Create new user
- `update()` - Update user
- `destroy()` - Delete user
- `suspend()` - Suspend user access
- `activate()` - Reactivate user

#### 3. App\Http\Controllers\Tenant\ProfileController
- `show()` - Get current user profile
- `update()` - Update profile information
- `updatePassword()` - Change password
- `uploadAvatar()` - Handle avatar upload

#### 4. App\Http\Controllers\Tenant\DashboardController
- `index()` - Render tenant dashboard
- `stats()` - Get tenant-specific statistics

## Service Layer

### Central Services

#### 1. App\Services\Central\AdminAuthenticationService
```php
class AdminAuthenticationService
{
    public function authenticate(array $credentials): array
    public function createSession(CentralUser $user): void
    public function invalidateSession(): void
    public function verifyTwoFactor(CentralUser $user, string $code): bool
    public function logActivity(CentralUser $user, string $action): void
}
```

#### 2. App\Services\Central\TenantUserManagementService
```php
class TenantUserManagementService
{
    public function getTenantUsers(Tenant $tenant, array $filters = []): LengthAwarePaginator
    public function createTenantUser(Tenant $tenant, array $data): TenantUser
    public function updateTenantUser(Tenant $tenant, TenantUser $user, array $data): TenantUser
    public function deleteTenantUser(Tenant $tenant, TenantUser $user): bool
    public function suspendUser(Tenant $tenant, TenantUser $user): bool
    public function activateUser(Tenant $tenant, TenantUser $user): bool
    public function getUserActivities(Tenant $tenant, TenantUser $user): Collection
    public function updateTenantUserStats(Tenant $tenant): void
}
```

#### 3. App\Services\Central\AdminDashboardService
```php
class AdminDashboardService
{
    public function getOverviewStats(): array
    public function getTenantStats(): Collection
    public function getUserStats(): array
    public function getRecentActivities(int $limit = 50): Collection
    public function getSystemHealth(): array
}
```

### Tenant Services

#### 1. App\Services\Tenant\TenantAuthenticationService
```php
class TenantAuthenticationService
{
    public function register(array $data): TenantUser
    public function authenticate(array $credentials): array
    public function createToken(TenantUser $user, string $name): PersonalAccessToken
    public function invalidateSession(TenantUser $user): void
    public function requestPasswordReset(string $email): bool
    public function resetPassword(string $token, string $password): bool
}
```

#### 2. App\Services\Tenant\TenantUserService
```php
class TenantUserService
{
    public function listUsers(array $filters = []): LengthAwarePaginator
    public function createUser(array $data): TenantUser
    public function updateUser(TenantUser $user, array $data): TenantUser
    public function deleteUser(TenantUser $user): bool
    public function suspendUser(TenantUser $user): bool
    public function activateUser(TenantUser $user): bool
    public function canUserManageOthers(TenantUser $user): bool
}
```

#### 3. App\Services\Tenant\ProfileService
```php
class ProfileService
{
    public function updateProfile(TenantUser $user, array $data): TenantUser
    public function changePassword(TenantUser $user, string $currentPassword, string $newPassword): bool
    public function uploadAvatar(TenantUser $user, UploadedFile $file): string
    public function removeAvatar(TenantUser $user): bool
}
```

## Actions (Single Responsibility)

### Central Actions

#### 1. App\Actions\Central\CreateTenantUserAction
```php
class CreateTenantUserAction
{
    public function execute(Tenant $tenant, array $userData): TenantUser
    {
        // Switch to tenant context
        // Create user
        // Send welcome email
        // Log activity
        // Update tenant stats
    }
}
```

#### 2. App\Actions\Central\SuspendTenantUserAction
```php
class SuspendTenantUserAction
{
    public function execute(Tenant $tenant, TenantUser $user, CentralUser $admin): bool
    {
        // Verify permissions
        // Switch to tenant context
        // Suspend user
        // Revoke all tokens
        // Log activity
        // Notify user
    }
}
```

#### 3. App\Actions\Central\GenerateTenantStatsAction
```php
class GenerateTenantStatsAction
{
    public function execute(Tenant $tenant): array
    {
        // Switch to tenant context
        // Count total users
        // Count active users
        // Calculate growth
        // Get activity metrics
    }
}
```

### Tenant Actions

#### 1. App\Actions\Tenant\RegisterUserAction
```php
class RegisterUserAction
{
    public function execute(array $data): TenantUser
    {
        // Validate data
        // Create user
        // Send verification email
        // Log registration
        // Create initial token
    }
}
```

#### 2. App\Actions\Tenant\AuthenticateUserAction
```php
class AuthenticateUserAction
{
    public function execute(array $credentials): array
    {
        // Validate credentials
        // Check user status
        // Create session/token
        // Update last login
        // Log activity
    }
}
```

#### 3. App\Actions\Tenant\ChangeUserRoleAction
```php
class ChangeUserRoleAction
{
    public function execute(TenantUser $user, string $newRole, TenantUser $changedBy): TenantUser
    {
        // Validate role change
        // Update user role
        // Log activity
        // Notify user
    }
}
```

## Middleware

### Central Middleware

#### 1. App\Http\Middleware\CentralAdminAuth
```php
class CentralAdminAuth
{
    public function handle($request, Closure $next)
    {
        // Verify user is authenticated
        // Verify user is central admin
        // Check for valid session
        // Log access
    }
}
```

#### 2. App\Http\Middleware\SuperAdminOnly
```php
class SuperAdminOnly
{
    public function handle($request, Closure $next)
    {
        // Verify super admin role
        // Log sensitive access
    }
}
```

### Tenant Middleware

#### 1. App\Http\Middleware\TenantUserAuth
```php
class TenantUserAuth
{
    public function handle($request, Closure $next)
    {
        // Verify tenant context
        // Verify user authentication
        // Check user is active
        // Verify user belongs to tenant
    }
}
```

#### 2. App\Http\Middleware\TenantRole
```php
class TenantRole
{
    public function handle($request, Closure $next, ...$roles)
    {
        // Check user has required role
        // Log permission check
    }
}
```

## Frontend Views (Central Admin)

### Layout Structure
```
resources/views/
├── layouts/
│   ├── admin.blade.php         # Main admin layout with Tailwind
│   └── auth.blade.php          # Authentication layout
├── central/
│   ├── auth/
│   │   ├── login.blade.php    # Admin login page
│   │   └── two-factor.blade.php # 2FA verification
│   ├── dashboard/
│   │   └── index.blade.php    # Main dashboard
│   ├── tenants/
│   │   ├── index.blade.php    # Tenant list
│   │   ├── show.blade.php     # Tenant details
│   │   └── users.blade.php    # Tenant users list
│   └── users/
│       ├── index.blade.php    # All users across tenants
│       ├── show.blade.php     # User details
│       └── edit.blade.php     # Edit user form
└── components/              
    ├── stats-card.blade.php   # Reusable stats component
    ├── user-table.blade.php   # User table component
    └── activity-feed.blade.php # Activity feed component
```

### Key Frontend Features
1. **Responsive Tailwind Design**: Mobile-first, responsive admin interface
2. **Real-time Updates**: WebSocket integration for live stats
3. **Advanced Filtering**: DataTables for user management
4. **Charts & Graphs**: Chart.js for visualization
5. **Toast Notifications**: User feedback for actions
6. **Modal Dialogs**: Confirm dangerous actions
7. **Bulk Operations**: Select multiple users for actions

## Queue Jobs

### Central Jobs

#### 1. App\Jobs\Central\SyncTenantUserStatsJob
```php
class SyncTenantUserStatsJob implements ShouldQueue
{
    public function handle(): void
    {
        // For each tenant
        // Count users
        // Update stats table
        // Cache results
    }
}
```

#### 2. App\Jobs\Central\CleanupInactiveUsersJob
```php
class CleanupInactiveUsersJob implements ShouldQueue
{
    public function handle(): void
    {
        // Find inactive users
        // Send warning emails
        // Suspend after grace period
        // Log actions
    }
}
```

### Tenant Jobs

#### 1. App\Jobs\Tenant\SendWelcomeEmailJob
```php
class SendWelcomeEmailJob implements ShouldQueue
{
    use InteractsWithTenancy;
    
    public function handle(): void
    {
        // Send welcome email
        // Include onboarding info
        // Log email sent
    }
}
```

#### 2. App\Jobs\Tenant\ProcessUserActivityJob
```php
class ProcessUserActivityJob implements ShouldQueue
{
    use InteractsWithTenancy;
    
    public function handle(): void
    {
        // Aggregate user activities
        // Generate insights
        // Update user metrics
    }
}
```

## Testing Strategy

### Central Tests

#### 1. Feature/Central/AdminAuthenticationTest
```php
class AdminAuthenticationTest extends TestCase
{
    public function test_admin_can_login_with_valid_credentials()
    public function test_admin_cannot_login_with_invalid_credentials()
    public function test_admin_session_expires_after_inactivity()
    public function test_super_admin_has_full_access()
    public function test_regular_admin_has_limited_access()
}
```

#### 2. Feature/Central/TenantUserManagementTest
```php
class TenantUserManagementTest extends TestCase
{
    public function test_admin_can_list_all_tenant_users()
    public function test_admin_can_create_user_in_tenant()
    public function test_admin_can_suspend_tenant_user()
    public function test_admin_can_delete_tenant_user()
    public function test_admin_activity_is_logged()
}
```

#### 3. Feature/Central/AdminDashboardTest
```php
class AdminDashboardTest extends TestCase
{
    public function test_dashboard_shows_correct_statistics()
    public function test_activity_feed_shows_recent_actions()
    public function test_charts_render_with_data()
    public function test_filters_work_correctly()
}
```

### Tenant Tests

#### 1. Feature/Tenant/TenantAuthenticationTest
```php
class TenantAuthenticationTest extends TestCase
{
    protected $tenancy = true;
    
    public function test_user_can_login_to_tenant()
    public function test_user_cannot_access_other_tenant()
    public function test_suspended_user_cannot_login()
    public function test_password_reset_flow_works()
}
```

#### 2. Feature/Tenant/UserManagementTest
```php
class UserManagementTest extends TestCase
{
    protected $tenancy = true;
    
    public function test_owner_can_manage_users()
    public function test_admin_can_manage_users()
    public function test_member_cannot_manage_users()
    public function test_user_roles_are_enforced()
}
```

#### 3. Feature/Tenant/ProfileTest
```php
class ProfileTest extends TestCase
{
    protected $tenancy = true;
    
    public function test_user_can_update_profile()
    public function test_user_can_change_password()
    public function test_user_can_upload_avatar()
    public function test_profile_validation_works()
}
```

## Implementation Phases

### Phase 1: Database and Models (2-3 days)
1. Create all central migrations
2. Create all tenant migrations
3. Implement Central models (CentralUser, CentralUserActivity, TenantUserManagement)
4. Implement Tenant models (TenantUser, TenantUserActivity)
5. Set up model relationships and scopes
6. Create model factories for testing

### Phase 2: Authentication Foundation (2-3 days)
1. Configure authentication guards (central_web, central_api, tenant_web, tenant_api)
2. Implement CentralAdminAuth middleware
3. Implement TenantUserAuth middleware
4. Create authentication services for both contexts
5. Set up session management
6. Implement token management

### Phase 3: Central Admin API (3-4 days)
1. Create AdminAuthController with login/logout
2. Create TenantUserManagementController with CRUD operations
3. Create AdminDashboardController for statistics
4. Implement services for admin operations
5. Add API routes in api.php
6. Create request validation classes
7. Implement API resources for responses

### Phase 4: Central Admin Frontend (3-4 days)
1. Set up Tailwind CSS configuration
2. Create admin layout template
3. Build login page with form
4. Create dashboard with statistics cards
5. Build tenant management interface
6. Create user management tables with filtering
7. Add activity feed component
8. Implement toast notifications

### Phase 5: Tenant Authentication (2-3 days)
1. Create Tenant AuthController
2. Implement registration flow (if enabled)
3. Build login/logout functionality
4. Create password reset flow
5. Add tenant auth routes
6. Create email templates

### Phase 6: Tenant User Management (2-3 days)
1. Create UserController for tenant context
2. Implement user CRUD operations
3. Add role-based permissions
4. Create ProfileController
5. Implement avatar upload
6. Add user activity logging

### Phase 7: Queue Jobs and Background Tasks (1-2 days)
1. Create stats synchronization jobs
2. Implement user cleanup jobs
3. Create welcome email jobs
4. Set up activity aggregation
5. Configure queue workers

### Phase 8: Testing (3-4 days)
1. Write central authentication tests
2. Create tenant user management tests
3. Test cross-tenant isolation
4. Verify role-based access
5. Test API endpoints
6. Create UI integration tests

### Phase 9: Security and Optimization (2-3 days)
1. Implement rate limiting
2. Add CSRF protection
3. Set up API throttling
4. Optimize database queries
5. Add caching where appropriate
6. Security audit

### Phase 10: Documentation and Deployment (1-2 days)
1. Create API documentation
2. Write user guides
3. Document admin procedures
4. Set up monitoring
5. Configure production environment
6. Deploy and verify

## Security Considerations

### Authentication Security
- Implement rate limiting on login endpoints (5 attempts per minute)
- Use bcrypt for password hashing
- Enforce strong password requirements
- Implement 2FA for central admins
- Session timeout after 30 minutes of inactivity
- Secure token storage with expiration

### Authorization Security
- Strict tenant isolation at database level
- Role-based access control (RBAC)
- Verify tenant context on every request
- Prevent cross-tenant data access
- Audit log for sensitive operations

### Data Protection
- Encrypt sensitive data at rest
- Use HTTPS for all communications
- Sanitize all user inputs
- Implement CSRF protection
- XSS prevention in blade templates
- SQL injection prevention via Eloquent

### API Security
- API rate limiting (100 requests per minute)
- Token-based authentication
- API versioning for backward compatibility
- Request validation on all endpoints
- Proper error handling without data leakage

## Performance Optimizations

### Database Optimizations
- Add indexes on frequently queried columns
- Use eager loading to prevent N+1 queries
- Implement query result caching
- Pagination for large datasets
- Database connection pooling

### Caching Strategy
- Cache user permissions (5 minutes)
- Cache tenant statistics (15 minutes)
- Cache dashboard data (5 minutes)
- Use Redis for session storage
- Implement cache warming

### Frontend Optimizations
- Lazy load components
- Implement infinite scrolling for large lists
- Use CDN for static assets
- Minify CSS and JavaScript
- Enable gzip compression

## Monitoring and Logging

### Activity Logging
- Log all authentication attempts
- Track user management operations
- Record permission changes
- Monitor API usage
- Track failed operations

### Performance Monitoring
- Query performance tracking
- API response time monitoring
- Queue job performance
- Memory usage tracking
- Error rate monitoring

### Alerting
- Failed login attempts threshold
- Suspicious activity patterns
- System errors
- Performance degradation
- Security violations

## Dependencies

### Required Packages
- **laravel/sanctum**: API authentication
- **spatie/laravel-permission**: Role management (optional, can use custom)
- **intervention/image**: Avatar processing
- **laravel/horizon**: Queue monitoring
- **barryvdh/laravel-debugbar**: Development debugging

### Frontend Dependencies
- **tailwindcss**: CSS framework
- **alpinejs**: Lightweight JavaScript framework
- **chart.js**: Data visualization
- **datatables**: Advanced table features
- **axios**: HTTP client

### Development Tools
- **laravel/telescope**: Debugging and monitoring
- **nunomaduro/larastan**: Static analysis
- **pestphp/pest**: Modern testing framework (optional)

## Configuration Files

### config/auth.php Modifications
```php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
    'central_web' => [
        'driver' => 'session',
        'provider' => 'central_users',
    ],
    'central_api' => [
        'driver' => 'sanctum',
        'provider' => 'central_users',
    ],
    'tenant_web' => [
        'driver' => 'session',
        'provider' => 'tenant_users',
    ],
    'tenant_api' => [
        'driver' => 'sanctum',
        'provider' => 'tenant_users',
    ],
],

'providers' => [
    'central_users' => [
        'driver' => 'eloquent',
        'model' => App\Models\Central\CentralUser::class,
    ],
    'tenant_users' => [
        'driver' => 'eloquent',
        'model' => App\Models\Tenant\TenantUser::class,
    ],
],
```

### Environment Variables
```env
# Central Admin
CENTRAL_ADMIN_DOMAIN=admin.minimeet.app
ENABLE_ADMIN_2FA=true
ADMIN_SESSION_LIFETIME=30

# Tenant Settings
ENABLE_TENANT_REGISTRATION=true
TENANT_USER_DEFAULT_ROLE=member
MAX_USERS_PER_TENANT=100

# Security
PASSWORD_MIN_LENGTH=8
LOGIN_RATE_LIMIT=5
API_RATE_LIMIT=100
```

## Success Metrics

### Technical Metrics
- API response time < 200ms
- Authentication success rate > 99%
- Zero cross-tenant data leaks
- 100% test coverage for auth flows
- Queue job success rate > 99.9%

### Business Metrics
- Admin can manage users within 3 clicks
- User onboarding time < 2 minutes
- Support ticket reduction by 40%
- User satisfaction score > 4.5/5
- System uptime > 99.9%

## Rollback Plan

### Phase-wise Rollback
1. Each phase can be rolled back independently
2. Database migrations include down() methods
3. Feature flags for gradual rollout
4. Backup before each deployment
5. Monitoring for immediate issue detection

### Emergency Procedures
1. Disable new feature via feature flag
2. Rollback database migrations if needed
3. Restore from backup if critical
4. Clear caches and restart services
5. Notify affected users

## Future Enhancements

### Phase 2 Features
- Single Sign-On (SSO) integration
- LDAP/Active Directory support
- Advanced role customization
- Bulk user import/export
- API key management

### Phase 3 Features
- User groups and teams
- Delegated administration
- Audit log interface
- Advanced reporting
- Mobile app support

## Notes for Developers

### Critical Implementation Points
1. **Always check tenant context** before any database operation
2. **Never mix Central and Tenant models** in same query
3. **Use proper middleware** for route protection
4. **Test tenant isolation** thoroughly
5. **Log all administrative actions** for audit trail

### Common Pitfalls to Avoid
1. Forgetting to switch tenant context in jobs
2. Using wrong database connection
3. Not validating tenant ownership
4. Inadequate rate limiting
5. Missing activity logging

### Development Workflow
1. Create feature branch from main
2. Implement with tests
3. Run full test suite including tenant tests
4. Code review focusing on multi-tenancy
5. Deploy to staging for QA
6. Production deployment with monitoring

This comprehensive specification provides a complete roadmap for implementing the user management system while maintaining strict multi-tenant architecture principles and Docker-first development practices.