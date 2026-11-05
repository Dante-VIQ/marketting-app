<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds content_hash (SHA-256 of raw HTML) to page_snapshots.
 *
 * Purpose: lets RunPublicScanJob skip 4 AI calls + 5 Tavily searches when
 * the page HTML hasn't changed since the last scan. The hash is computed from
 * the raw HTTP response body before any parsing, so even whitespace changes
 * are detected and no AI analysis is wasted on truly unchanged pages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_snapshots', function (Blueprint $table) {
            // nullable so existing rows remain valid; filled on every new scan
            $table->string('content_hash', 64)->nullable()->after('metadata')
                  ->comment('SHA-256 of the raw HTML fetched from the URL');

            $table->index('content_hash');
        });
    }

    public function down(): void
    {
        Schema::table('page_snapshots', function (Blueprint $table) {
            $table->dropIndex(['content_hash']);
            $table->dropColumn('content_hash');
        });
    }
};
