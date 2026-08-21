<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('action_id')->nullable();
            $table->string('url');
            $table->string('page_type')->nullable(); // blog, service, contact, landing, home, about, product, other

            // Content
            $table->string('title')->nullable();
            $table->json('headings')->nullable(); // {h1: "Title", h2: ["Heading1", "Heading2"]}
            $table->longText('content')->nullable();
            $table->integer('word_count')->default(0);
            $table->decimal('readability_score', 5, 2)->nullable();

            // SEO
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords')->nullable();
            $table->string('canonical_url')->nullable();
            $table->json('og_tags')->nullable();
            $table->json('schema_markup')->nullable();

            // Technical
            $table->integer('load_time_ms')->nullable();
            $table->boolean('is_mobile_friendly')->default(true);
            $table->json('broken_links')->nullable();
            $table->json('internal_links')->nullable();
            $table->json('external_links')->nullable();

            // Images
            $table->integer('image_count')->default(0);
            $table->json('images')->nullable(); // [{url, alt, width, height}]

            // Analysis
            $table->json('topics_covered')->nullable();
            $table->json('topics_missing')->nullable();
            $table->json('content_gaps')->nullable();
            $table->json('recommendations')->nullable();
            $table->json('metadata')->nullable();

            // Status
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('scraped_at')->nullable();
            $table->timestamps();

            $table->foreign('brand_id')
            ->references('id')
            ->on('brands')
            ->onDelete('cascade');

            $table->foreign('action_id')
            ->references('id')
            ->on('ai_actions')
            ->onDelete('set null');

            $table->index(['brand_id', 'url']);
            $table->index(['brand_id', 'status']);
            $table->index(['brand_id', 'page_type']);
            $table->index('scraped_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_snapshots');
    }
};
