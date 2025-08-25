<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenant_users_management', function (Blueprint $table) {
            // User role counts
            $table->integer('admin_users')->default(0)->after('active_users');
            $table->integer('manager_users')->default(0)->after('admin_users');
            $table->integer('regular_users')->default(0)->after('manager_users');
            
            // User activity metrics
            $table->integer('new_users_30d')->default(0)->after('regular_users');
            $table->integer('recently_active_users')->default(0)->after('new_users_30d');
            
            // Activity metrics
            $table->integer('total_activities')->default(0)->after('recently_active_users');
            $table->integer('recent_activities_24h')->default(0)->after('total_activities');
            $table->integer('recent_logins_7d')->default(0)->after('recent_activities_24h');
            
            // JSON data for detailed breakdowns
            $table->json('activity_breakdown')->nullable()->after('recent_logins_7d');
            $table->json('most_active_users')->nullable()->after('activity_breakdown');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_users_management', function (Blueprint $table) {
            $table->dropColumn([
                'admin_users',
                'manager_users', 
                'regular_users',
                'new_users_30d',
                'recently_active_users',
                'total_activities',
                'recent_activities_24h',
                'recent_logins_7d',
                'activity_breakdown',
                'most_active_users'
            ]);
        });
    }
};
