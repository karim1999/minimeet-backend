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
        Schema::create('tenant_users_management', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->integer('user_count')->default(0);
            $table->integer('active_users')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_users_management');
    }
};
