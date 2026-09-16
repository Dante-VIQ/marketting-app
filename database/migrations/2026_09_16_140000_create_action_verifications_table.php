<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('action_verifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('action_id');

            // Verification phase: immediate, hour_1, day_1
            $table->string('phase');

            // Metrics snapshot
            $table->json('metrics_before')->nullable();
            $table->json('metrics_after')->nullable();
            $table->json('metric_deltas')->nullable();

            // Outcome
            $table->boolean('was_successful')->default(false);
            $table->float('improvement_score')->nullable();

            // Rollback
            $table->boolean('rollback_triggered')->default(false);
            $table->text('rollback_reason')->nullable();
            $table->timestamp('rollback_at')->nullable();

            $table->timestamp('verified_at');
            $table->timestamps();

            $table->foreign('brand_id')->references('id')->on('brands')->onDelete('cascade');
            $table->foreign('action_id')->references('id')->on('ai_actions')->onDelete('cascade');

            $table->index(['brand_id', 'action_id']);
            $table->index(['brand_id', 'phase']);
            $table->unique(['action_id', 'phase']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_verifications');
    }
};