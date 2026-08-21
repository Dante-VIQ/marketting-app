<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('brief_id');
            $table->string('title');
            $table->enum('category', [
                'seo', 
                'content', 
                'social', 
                'email', 
                'web_copy', 
                'campaign', 
                'strategy', 
                'analytics'
            ]);
            $table->text('description');
            $table->longText('suggested_content')->nullable();
            $table->longText('content_draft')->nullable(); // Full blog posts, web copy
            $table->string('target_platform')->nullable(); // facebook, linkedin, twitter, blog, email
            $table->string('target_url')->nullable(); // For web copy or SEO
            $table->decimal('estimated_impact', 15, 2)->nullable();
            $table->enum('status', [
                'pending', 
                'approved', 
                'rejected', 
                'content_generated', 
                'published', 
                'completed'
            ])->default('pending');
            $table->enum('rejection_reason', [
                'too_short',
                'tone_wrong',
                'factually_incorrect',
                'off_brand',
                'duplicate',
                'low_priority',
                'other'
            ])->nullable();
            $table->text('rejection_notes')->nullable();
            $table->integer('priority')->default(1); // 1-5, 5 being highest
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->decimal('actual_revenue_impact', 15, 2)->nullable(); // Filled later
            $table->timestamps();

            $table->foreign('brand_id')
                ->references('id')
                ->on('brands')
                ->onDelete('cascade');

            $table->foreign('brief_id')
                ->references('id')
                ->on('ai_briefs')
                ->onDelete('cascade');

            $table->index(['brand_id', 'status']);
            $table->index(['brand_id', 'category']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_actions');
    }
};