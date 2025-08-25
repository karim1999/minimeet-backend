<?php

namespace App\Models;

use App\Models\Central\TenantUserManagement;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains, HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): TenantFactory
    {
        return TenantFactory::new();
    }

    /**
     * Get the user management statistics for this tenant.
     */
    public function userManagement(): HasOne
    {
        return $this->hasOne(TenantUserManagement::class, 'tenant_id');
    }
}
