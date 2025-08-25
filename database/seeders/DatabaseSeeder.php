<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'admin',
        ]);

        // Create a superadmin user
        User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'role' => 'super_admin',
        ]);

        // Create a support user
        User::factory()->create([
            'name' => 'Support User',
            'email' => 'support@example.com',
            'role' => 'support',
        ]);
    }
}
