<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_briefs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id');
            $table->date('brief_date');
            $table->string('fingerprint')->unique(); // SHA256 hash of input data to prevent duplicates
            $table->longText('strategic_diagnosis');
            $table->decimal('estimated_revenue_impact', 15, 2)->nullable();
            $table->decimal('confidence_score', 5, 2)->nullable(); // 0-100
            $table->json('raw_llm_output')->nullable();
            $table->string('ai_provider')->default('ollama'); // ollama, openai, anthropic
            $table->string('model_used')->nullable();
            $table->integer('tokens_used')->nullable();
            $table->float('response_time_ms')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();

            $table->foreign('brand_id')
                ->references('id')
                ->on('brands')
                ->onDelete('cascade');

            $table->foreign('approved_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index(['brand_id', 'brief_date']);
            $table->index('fingerprint');
            $table->index(['brand_id', 'is_approved']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_briefs');
    }
};