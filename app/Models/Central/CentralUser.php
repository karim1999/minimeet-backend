<?php

namespace App\Models\Central;

use App\Models\Tenant;
use App\Models\User;
use Database\Factories\CentralUserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CentralUser extends User
{
    use HasFactory;

    protected $connection = 'central';

    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_central',
        'last_login_at',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_central' => 'boolean',
            'last_login_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): CentralUserFactory
    {
        return CentralUserFactory::new();
    }

    /**
     * Get the activities for the central user.
     */
    public function activities(): HasMany
    {
        return $this->hasMany(CentralUserActivity::class, 'user_id');
    }

    /**
     * Get the tenants managed by this central user.
     */
    public function managedTenants(): HasMany
    {
        return $this->hasMany(Tenant::class, 'created_by');
    }

    /**
     * Scope a query to only include admin users.
     */
    public function scopeAdmins(Builder $query): Builder
    {
        return $query->whereIn('role', ['admin', 'super_admin', 'support']);
    }

    /**
     * Scope a query to only include active users.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_central', true);
    }

    /**
     * Check if the user is a super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /**
     * Check if the user is an admin (any admin level).
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin']);
    }

    /**
     * Check if the user can manage a specific tenant.
     */
    public function canManageTenant(Tenant $tenant): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->managedTenants()->where('id', $tenant->id)->exists();
    }

    /**
     * Log an activity for the central user.
     */
    public function logActivity(string $action, $model = null, array $metadata = []): void
    {
        $this->activities()->create([
            'action' => $action,
            'model_type' => $model ? get_class($model) : null,
            'model_id' => $model?->getKey(),
            'metadata' => $metadata,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Update the last login timestamp.
     */
    public function updateLastLogin(): void
    {
        $this->update([
            'last_login_at' => now(),
        ]);
    }

    /**
     * Get the display name for the role.
     */
    public function getRoleDisplayName(): string
    {
        return match ($this->role) {
            'super_admin' => 'Super Administrator',
            'admin' => 'Administrator',
            'support' => 'Support',
            default => 'Unknown'
        };
    }
}
