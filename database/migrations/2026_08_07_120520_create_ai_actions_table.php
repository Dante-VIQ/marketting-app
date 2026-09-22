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

            $table->foreignId('brand_id')
                ->constrained('brands')
                ->cascadeOnDelete();

            // Actions can exist without a brief (agent-created).
            // If the brief is deleted, the action survives with brief_id = NULL.
            $table->foreignId('brief_id')
                ->nullable()
                ->constrained('ai_briefs')
                ->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            // VARCHAR, not ENUM.
            // The agent invents categories over time — 'escalation' was the
            // first one that broke the enum. There will be more.
            $table->string('category', 32);

            $table->longText('suggested_content')->nullable();
            $table->longText('content_draft')->nullable();

            $table->string('target_platform')->nullable();
            $table->string('target_url')->nullable();
            $table->string('target_keyword')->nullable();

            $table->decimal('estimated_impact', 15, 2)->nullable();
            $table->decimal('actual_revenue_impact', 15, 2)->nullable();

            // VARCHAR, not ENUM.
            // Statuses evolve: 'escalated', 'rolled_back', and others
            // are already in the app's vocabulary.
            $table->string('status', 32)->default('pending');

            $table->unsignedTinyInteger('priority')->default(3);

            // Provenance: 'agent', 'brief', 'scheduler', 'human'
            $table->string('origin', 32)->nullable();

            // Opportunity linkage (recurring-issue tracking + dedup).
            // Stable key is the same across days for the same underlying problem.
            // Fingerprint is day-scoped for idempotency.
            $table->string('opportunity_fingerprint', 64)->nullable();
            $table->string('opportunity_stable_key', 64)->nullable();

            // Human review of the action itself
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->text('review_notes')->nullable();

            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason', 32)->nullable();
            $table->text('rejection_notes')->nullable();

            // Retry handling after rejection
            $table->string('retry_status', 16)->nullable();   // none | authorized | held
            $table->text('expected_retry_approach')->nullable();

            // Tracks when the agent has been notified of a human outcome
            $table->timestamp('agent_notified_at')->nullable();

            // Execution
            $table->timestamp('executed_at')->nullable();

            // Escalation responses (only set when category = 'escalation')
            $table->string('human_response', 32)->nullable();  // investigate | retry | resolve | snooze
            $table->text('human_response_notes')->nullable();
            $table->timestamp('human_response_at')->nullable();
            $table->date('snooze_until')->nullable();

            // Multi-phase verification (hour-1 / day-1 / week-1 planned)
            $table->boolean('verified_immediate')->default(false);
            $table->boolean('verified_hour_1')->default(false);
            $table->boolean('verified_day_1')->default(false);
            $table->string('verification_status', 32)->nullable(); // pending | verified | failed | rolled_back
            $table->json('metrics_at_execution')->nullable();
            $table->timestamp('verify_at_hour_1')->nullable();
            $table->timestamp('verify_at_day_1')->nullable();

            $table->timestamps();

            // Indexes for the queries the app actually runs
            $table->index(['brand_id', 'status']);
            $table->index(['brand_id', 'category']);
            $table->index(['brand_id', 'created_at']);
            $table->index(['brand_id', 'executed_at']);
            $table->index('brief_id');
            $table->index('status');
            $table->index('opportunity_stable_key');
            $table->index('opportunity_fingerprint');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_actions');
    }
};