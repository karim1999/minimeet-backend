<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use App\Models\Tenant;

abstract class TestCase extends BaseTestCase
{
    protected $tenancy = false;

    public function setUp(): void
    {
        parent::setUp();

        if ($this->tenancy) {
            $this->initializeTenancy();
        }
    }

    public function tearDown(): void
    {
        if ($this->tenancy) {
            tenancy()->end();
        } else {
            // Clean up any tenants created during central tests
            Tenant::where('id', '!=', 'testing')->each(function ($tenant) {
                $tenant->delete();
            });;
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
            'id' => 'testing'
        ]);

        $domain = $tenant->domains()->create([
            'domain' => 'testing',
        ]);

        tenancy()->initialize($tenant);
    }
}
