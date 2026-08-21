<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardian_incidents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->unsignedBigInteger('policy_id')->nullable();
            $table->string('type'); // policy_violation, suspicious_activity, system_error
            $table->string('severity'); // low, medium, high, critical
            $table->text('description');
            $table->json('context')->nullable();
            $table->json('metadata')->nullable();
            $table->enum('status', ['open', 'investigating', 'resolved', 'dismissed'])->default('open');
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamps();

            $table->foreign('brand_id')
                ->references('id')
                ->on('brands')
                ->onDelete('set null');

            $table->foreign('policy_id')
                ->references('id')
                ->on('guardian_policies')
                ->onDelete('set null');

            $table->foreign('resolved_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index(['brand_id', 'status']);
            $table->index('severity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_incidents');
    }
};