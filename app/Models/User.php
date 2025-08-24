<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
        ];
    }

    public function isCentralUser(): bool
    {
        return $this->getConnectionName() === 'central';
    }

    public function isTenantUser(): bool
    {
        return ! $this->isCentralUser();
    }

    public function ownedTenants(): HasMany
    {
        return $this->hasMany(\App\Models\Tenant::class, 'owner_id');
    }

    public function createTenantToken(string $name, array $abilities = ['*']): PersonalAccessToken
    {
        $tenantId = tenant('id');

        if (! $tenantId) {
            throw new \Exception('Cannot create tenant token outside of tenant context');
        }

        $tenantAbilities = array_map(
            fn ($ability) => "tenant:$tenantId:$ability",
            $abilities
        );

        return $this->createToken($name, $tenantAbilities);
    }
}
