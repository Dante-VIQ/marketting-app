<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listening_queries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('term', 120);
            $table->string('kind', 12);                       // brand | competitor | topic
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_checked_at')->nullable();
            $table->json('source_status')->nullable();        // {"reddit":"ok","google_news":"error: ..."}
            $table->timestamps();

            $table->unique(['brand_id', 'kind', 'term']);
        });

        Schema::create('mentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listening_query_id')->constrained('listening_queries')->cascadeOnDelete();
            $table->string('source', 16);                     // google_news | reddit | hackernews
            $table->char('url_hash', 40);
            $table->string('title', 500);
            $table->string('url', 1000);
            $table->text('snippet')->nullable();
            $table->string('author')->nullable();
            $table->string('publisher')->nullable();          // news outlet / subreddit
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('score')->nullable();     // upvotes / points
            $table->unsignedInteger('comments')->nullable();
            $table->string('sentiment', 10)->nullable();      // positive | neutral | negative (heuristic)
            $table->boolean('is_dismissed')->default(false);
            $table->timestamps();

            $table->unique(['listening_query_id', 'url_hash']);
            $table->index(['brand_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentions');
        Schema::dropIfExists('listening_queries');
    }
};
