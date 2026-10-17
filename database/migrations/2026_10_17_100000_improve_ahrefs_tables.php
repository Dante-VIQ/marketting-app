<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ahrefs_site_stats', function (Blueprint $table) {
            $table->integer('keywords_top3')->nullable()->after('organic_keywords');
            // A missing value must be NULL, never a fake 0.
            $table->integer('backlinks')->nullable()->default(null)->change();
            $table->integer('referring_domains')->nullable()->default(null)->change();
            $table->integer('organic_keywords')->nullable()->default(null)->change();
        });

        // Historical rows were written by code that never fetched these metrics (they were
        // always 0) and stored Ahrefs rank in url_rating. Mark them unknown instead of 0.
        DB::table('ahrefs_site_stats')
            ->where('backlinks', 0)->where('referring_domains', 0)->where('organic_keywords', 0)
            ->update(['backlinks' => null, 'referring_domains' => null, 'organic_keywords' => null,
                      'organic_traffic' => null, 'traffic_value' => null]);
        DB::table('ahrefs_site_stats')->update(['url_rating' => null]);

        Schema::table('ahrefs_keywords', function (Blueprint $table) {
            $table->integer('previous_position')->nullable()->after('position');
            $table->integer('position_change')->nullable()->after('previous_position'); // + = moved up
            $table->integer('traffic')->nullable()->after('search_volume');
            $table->string('intent', 20)->nullable()->after('difficulty');
            $table->boolean('is_branded')->nullable()->after('intent');
        });

        Schema::table('ahrefs_backlinks', function (Blueprint $table) {
            $table->boolean('is_lost')->default(false)->after('is_nofollow');
            $table->string('lost_reason', 40)->nullable()->after('is_lost');
            $table->boolean('is_spam')->default(false)->after('lost_reason');
            $table->unique(['brand_id', 'source_url', 'target_url'], 'ahrefs_backlinks_unique');
        });
    }

    public function down(): void
    {
        Schema::table('ahrefs_backlinks', function (Blueprint $table) {
            $table->dropUnique('ahrefs_backlinks_unique');
            $table->dropColumn(['is_lost', 'lost_reason', 'is_spam']);
        });
        Schema::table('ahrefs_keywords', function (Blueprint $table) {
            $table->dropColumn(['previous_position', 'position_change', 'traffic', 'intent', 'is_branded']);
        });
        Schema::table('ahrefs_site_stats', function (Blueprint $table) {
            $table->dropColumn('keywords_top3');
            $table->integer('backlinks')->default(0)->nullable(false)->change();
            $table->integer('referring_domains')->default(0)->nullable(false)->change();
            $table->integer('organic_keywords')->default(0)->nullable(false)->change();
        });
    }
};
