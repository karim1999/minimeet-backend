<?php

namespace Tests;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected $tenancy = false;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->tenancy) {
            $this->initializeTenancy();
        } else {
            $this->setupCentralContext();
            $this->clearLoginAttempts();
        }
    }

    protected function tearDown(): void
    {
        if ($this->tenancy) {
            tenancy()->end();
            
            // Clean up the test tenant and its database
            $testTenant = Tenant::find('testing');
            if ($testTenant) {
                $testTenant->delete();
            }
        } else {
            // Clean up any tenants created during central tests
            Tenant::where('id', '!=', 'testing')->each(function ($tenant) {
                $tenant->delete();
            });
        }

        parent::tearDown();
    }

    public function initializeTenancy()
    {
        // Remove existing tenant with the same name if it exists
        $existingTenant = Tenant::where('id', 'testing')->first();
        if ($existingTenant) {
            $existingTenant->delete();
        }

        $tenant = Tenant::create([
            'id' => 'testing',
        ]);

        $domain = $tenant->domains()->create([
            'domain' => 'testing.localhost',
        ]);

        tenancy()->initialize($tenant);

        // Run tenant migrations to create database tables
        $this->artisan('migrate', ['--database' => 'tenant', '--path' => 'database/migrations/tenant']);

        // Set the app URL to match the tenant domain
        config(['app.url' => 'http://testing.localhost']);
        \URL::useOrigin(config('app.url'));
    }

    public function setupCentralContext()
    {
        // Set the app URL to match a central domain
        $centralDomain = config('tenancy.central_domains')[0] ?? 'localhost';
        config(['app.url' => "http://{$centralDomain}"]);
        \URL::useOrigin(config('app.url'));
    }

    protected function clearLoginAttempts(): void
    {
        // Clear login attempts to prevent lockouts during tests
        \DB::table('login_attempts')->delete();
    }
}
