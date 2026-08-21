<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('campaign_id')->nullable();
            
            // Personal Information
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->string('title')->nullable();
            
            // Lead Details
            $table->text('message')->nullable();
            $table->enum('source', ['website', 'social', 'email', 'referral', 'event', 'other'])->default('website');
            $table->enum('category', ['travel', 'software', 'seo', 'consulting', 'other'])->nullable();
            $table->enum('status', ['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'won', 'lost'])->default('new');
            $table->string('score')->nullable(); // hot, warm, cold
            $table->decimal('estimated_value', 15, 2)->nullable();
            
            // AI Generated
            $table->text('ai_summary')->nullable();
            $table->text('ai_suggested_response')->nullable();
            $table->json('ai_metadata')->nullable();
            
            // Tracking
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamp('follow_up_at')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            
            $table->timestamps();

            // Foreign Keys
            $table->foreign('brand_id')
                ->references('id')
                ->on('brands')
                ->onDelete('cascade');

            $table->foreign('campaign_id')
                ->references('id')
                ->on('campaigns')
                ->onDelete('set null');

            $table->foreign('assigned_to')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            // Indexes
            $table->index(['brand_id', 'status']);
            $table->index(['brand_id', 'category']);
            $table->index(['brand_id', 'source']);
            $table->index('email');
            $table->index('follow_up_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};