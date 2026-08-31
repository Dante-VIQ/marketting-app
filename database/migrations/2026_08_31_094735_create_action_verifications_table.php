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
            $table->string('action_name');
            $table->string('opportunity_type')->nullable();
            $table->unsignedBigInteger('experience_id')->nullable();
            $table->json('before_metrics')->nullable();
            $table->json('after_metrics')->nullable();
            $table->decimal('improvement_percentage', 8, 2)->nullable();
            $table->boolean('was_successful')->default(false);
            $table->string('status')->default('pending'); // pending, verifying, verified, failed
            $table->text('verification_notes')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->foreign('brand_id')
                ->references('id')
                ->on('brands')
                ->onDelete('cascade');

            $table->foreign('experience_id')
                ->references('id')
                ->on('agent_experiences')
                ->onDelete('set null');

            $table->index(['brand_id', 'status']);
            $table->index(['brand_id', 'action_name']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_verifications');
    }
};