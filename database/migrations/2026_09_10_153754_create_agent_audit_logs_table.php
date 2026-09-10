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
    Schema::create('agent_audit_logs', function (Blueprint $table) {
        $table->id();
        $table->string('ip_address', 45)->index();
        $table->string('method', 10);
        $table->string('path');
        $table->string('user_agent')->nullable();
        $table->unsignedBigInteger('brand_id')->nullable()->index();
        $table->json('metadata')->nullable();
        $table->timestamps();

        $table->index('created_at');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_audit_logs');
    }
};
