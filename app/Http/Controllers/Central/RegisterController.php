<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\RegisterRequest;
use App\Http\Resources\Central\TenantResource;
use App\Http\Resources\Central\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;

class RegisterController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Create central user (tenant owner)
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Create tenant
        $tenant = Tenant::create([
            'id' => Str::uuid(),
            'data' => [
                'company_name' => $validated['company_name'],
                'owner_id' => $user->id,
            ],
        ]);

        // Create domain
        Domain::create([
            'domain' => $validated['domain'],
            'tenant_id' => $tenant->id,
        ]);

        // Load domains relationship for the resource
        $tenant->load('domains');

        return ApiResponse::created(
            'Tenant created successfully',
            [
                'user' => new UserResource($user),
                'tenant' => new TenantResource($tenant),
            ]
        );
    }
}
