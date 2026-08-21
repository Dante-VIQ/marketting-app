<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ahrefs Backlink Data
        Schema::create('ahrefs_backlinks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id');
            $table->string('target_url');
            $table->string('source_url');
            $table->string('anchor_text')->nullable();
            $table->string('source_domain');
            $table->string('source_domain_rating')->nullable();
            $table->string('source_page_title')->nullable();
            $table->boolean('is_follow')->default(false);
            $table->boolean('is_nofollow')->default(false);
            $table->date('first_seen_at')->nullable();
            $table->date('last_seen_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('brand_id')
                ->references('id')
                ->on('brands')
                ->onDelete('cascade');

            $table->index(['brand_id', 'target_url']);
            $table->index(['brand_id', 'source_domain']);
            $table->index('last_seen_at');
        });

        // Ahrefs Keyword Rankings
        Schema::create('ahrefs_keywords', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id');
            $table->string('keyword');
            $table->string('target_url')->nullable();
            $table->integer('position')->nullable();
            $table->integer('search_volume')->nullable();
            $table->string('difficulty')->nullable();
            $table->string('competition')->nullable();
            $table->date('tracked_date');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('brand_id')
                ->references('id')
                ->on('brands')
                ->onDelete('cascade');

            $table->index(['brand_id', 'keyword']);
            $table->index(['brand_id', 'tracked_date']);
            $table->unique(['brand_id', 'keyword', 'tracked_date'], 'ahrefs_keywords_unique');
        });

        // Ahrefs Site Stats
        Schema::create('ahrefs_site_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id');
            $table->string('domain');
            $table->integer('domain_rating')->nullable();
            $table->integer('url_rating')->nullable();
            $table->integer('backlinks')->default(0);
            $table->integer('referring_domains')->default(0);
            $table->integer('organic_keywords')->default(0);
            $table->integer('organic_traffic')->nullable();
            $table->integer('traffic_value')->nullable();
            $table->date('tracked_date');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('brand_id')
                ->references('id')
                ->on('brands')
                ->onDelete('cascade');

            $table->index(['brand_id', 'domain']);
            $table->index(['brand_id', 'tracked_date']);
            $table->unique(['brand_id', 'domain', 'tracked_date'], 'ahrefs_site_stats_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ahrefs_site_stats');
        Schema::dropIfExists('ahrefs_keywords');
        Schema::dropIfExists('ahrefs_backlinks');
    }
};