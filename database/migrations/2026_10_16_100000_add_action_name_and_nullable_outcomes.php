<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-action frequency caps: ai_actions only stored a coarse category.
        Schema::table('ai_actions', function (Blueprint $table) {
            $table->string('action_name', 64)->nullable()->after('category');
            $table->index(['brand_id', 'action_name', 'created_at']);
        });

        // NULL = outcome not known yet (pending / human-attributed); must not count as failure.
        Schema::table('agent_experiences', function (Blueprint $table) {
            $table->boolean('was_successful')->nullable()->default(null)->change();
        });

        Schema::create('agent_cycle_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32);              // completed | idle | failed | skipped
            $table->json('summary')->nullable();       // counts, per-opportunity results, errors
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['brand_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_cycle_runs');

        Schema::table('agent_experiences', function (Blueprint $table) {
            $table->boolean('was_successful')->default(false)->nullable(false)->change();
        });

        Schema::table('ai_actions', function (Blueprint $table) {
            $table->dropIndex(['brand_id', 'action_name', 'created_at']);
            $table->dropColumn('action_name');
        });
    }
};
