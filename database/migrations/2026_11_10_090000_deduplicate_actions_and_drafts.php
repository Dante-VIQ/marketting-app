<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deduplication hardening for ai_actions and content_drafts.
 *
 * Changes:
 *
 * 1. ai_actions — unique index on (brand_id, opportunity_stable_key) so the
 *    DB itself prevents inserting the same conceptual action twice, even if
 *    PHP code races. The stable key is a hash of brand+category+title-tokens,
 *    computed by BriefGeneratorService before insert.
 *
 * 2. content_drafts — unique index on action_id (nulls excluded by MySQL/Postgres)
 *    so an action can only ever have one draft. Existing duplicate rows are
 *    collapsed to the most-recent draft before the index is added.
 *
 * 3. ai_actions — extend the duplicate-detection window index to 30 days by
 *    adding a compound index on (brand_id, status, created_at) for fast lookups
 *    in BriefGeneratorService::isDuplicateAction().
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Collapse duplicate content_drafts before adding unique constraint ──
        // Keep the newest draft per action_id; delete the rest.
        DB::statement("
            DELETE cd1
            FROM content_drafts cd1
            INNER JOIN content_drafts cd2
                ON  cd1.action_id = cd2.action_id
                AND cd1.id < cd2.id
            WHERE cd1.action_id IS NOT NULL
        ");

        // ── 2. Unique index: one draft per action ─────────────────────────────────
        // MySQL ignores NULLs in unique indexes, so drafts without an action_id
        // are unaffected.
        Schema::table('content_drafts', function (Blueprint $table) {
            $table->unique('action_id', 'content_drafts_action_id_unique');
        });

        // ── 3. Unique index: one active action per stable key per brand ───────────
        // opportunity_stable_key is set by BriefGeneratorService before insert.
        // Rows that don't have one yet (old data, manual inserts) are unaffected
        // because MySQL allows multiple NULLs in a unique index.
        Schema::table('ai_actions', function (Blueprint $table) {
            $table->unique(
                ['brand_id', 'opportunity_stable_key'],
                'ai_actions_brand_stable_key_unique'
            );
        });

        // ── 4. Compound index for fast 30-day duplicate window queries ────────────
        Schema::table('ai_actions', function (Blueprint $table) {
            $table->index(
                ['brand_id', 'status', 'category', 'created_at'],
                'ai_actions_dedup_window_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('content_drafts', function (Blueprint $table) {
            $table->dropUnique('content_drafts_action_id_unique');
        });

        Schema::table('ai_actions', function (Blueprint $table) {
            $table->dropUnique('ai_actions_brand_stable_key_unique');
            $table->dropIndex('ai_actions_dedup_window_idx');
        });
    }
};
