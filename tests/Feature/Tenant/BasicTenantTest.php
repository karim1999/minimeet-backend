<?php

namespace Feature\Tenant;

use Tests\TestCase;

class BasicTenantTest extends TestCase
{
    protected $tenancy = true;

    public function test_routes_are_working(): void
    {
        $response = $this->getJson('/');
        $response->assertStatus(200);
    }
}
