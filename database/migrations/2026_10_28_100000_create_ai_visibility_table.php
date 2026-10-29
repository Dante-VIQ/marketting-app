<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores AI visibility audit results per brand.
 * One row per audit run; each row holds the full JSON report plus a score.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_visibility_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();

            // Score 0–100 derived from mention rate across probes
            $table->unsignedTinyInteger('score')->default(0);

            // How many probes were sent and how many returned a brand mention
            $table->unsignedSmallInteger('probes_sent')->default(0);
            $table->unsignedSmallInteger('probes_hit')->default(0);

            // Full structured report as JSON
            // {
            //   "queries": [{"query": "...", "mentioned": true, "context": "..."}],
            //   "competitors": [{"name": "...", "score": 42}],
            //   "recommendations": [{"priority": "high", "action": "...", "reason": "..."}]
            // }
            $table->json('report')->nullable();

            // Plain-text one-paragraph summary the AI generated
            $table->text('summary')->nullable();

            $table->string('status')->default('pending'); // pending | running | done | failed
            $table->string('error')->nullable();

            $table->timestamp('audited_at')->nullable();
            $table->timestamps();

            $table->index(['brand_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_visibility_audits');
    }
};
