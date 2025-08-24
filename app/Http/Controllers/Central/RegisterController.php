<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;

class RegisterController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'company_name' => ['required', 'string', 'max:255'],
            'domain' => ['required', 'string', 'max:255', 'unique:domains,domain'],
        ]);

        // Create central user (tenant owner)
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Create tenant
        $tenant = Tenant::create([
            'id' => Str::uuid(),
            'data' => [
                'company_name' => $request->company_name,
                'owner_id' => $user->id,
            ],
        ]);

        // Create domain
        Domain::create([
            'domain' => $request->domain,
            'tenant_id' => $tenant->id,
        ]);

        return response()->json([
            'message' => 'Tenant created successfully',
            'user' => $user,
            'tenant' => [
                'id' => $tenant->id,
                'company_name' => $request->company_name,
                'domain' => $request->domain,
            ],
        ], 201);
    }
}
