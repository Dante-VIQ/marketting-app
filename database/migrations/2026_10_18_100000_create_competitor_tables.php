<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('domain');                       // bare host, e.g. example.com
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['brand_id', 'domain']);
        });

        Schema::create('competitor_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competitor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->date('captured_on');
            $table->string('status', 16)->default('ok');    // ok | error
            $table->text('error')->nullable();
            $table->string('homepage_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('h1')->nullable();
            $table->unsignedInteger('sitemap_url_count')->nullable();   // null = no sitemap found
            $table->unsignedInteger('posts_last_30d')->nullable();      // null = could not tell
            $table->string('cadence_basis', 16)->nullable();            // feed | sitemap
            $table->timestamp('latest_post_at')->nullable();
            $table->string('feed_url')->nullable();
            $table->json('top_terms')->nullable();                      // term => count
            $table->json('recent_posts')->nullable();                   // [{title,url,date}]
            $table->timestamps();

            $table->unique(['competitor_id', 'captured_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_snapshots');
        Schema::dropIfExists('competitors');
    }
};
