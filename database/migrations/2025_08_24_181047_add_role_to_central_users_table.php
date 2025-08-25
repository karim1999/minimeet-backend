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
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['super_admin', 'admin', 'support'])->default('admin')->after('email');
            $table->boolean('is_central')->default(true)->after('role');
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
            $table->json('metadata')->nullable()->after('last_login_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'is_central', 'last_login_at', 'metadata']);
        });
    }
};
