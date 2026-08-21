<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id');
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content')->nullable();
            $table->text('excerpt')->nullable();
            $table->string('featured_image')->nullable();
            $table->string('author')->nullable();
            $table->json('tags')->nullable();
            $table->json('categories')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->json('seo_data')->nullable();

            // Performance metrics
            $table->integer('views')->default(0);
            $table->integer('leads_generated')->default(0);
            $table->integer('bookings_generated')->default(0);
            $table->decimal('revenue_generated', 15, 2)->default(0);
            $table->decimal('average_time_on_page', 8, 2)->nullable();
            $table->decimal('bounce_rate', 8, 2)->nullable();

            // Status
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->enum('source', ['manual', 'ai_generated'])->default('manual');
            $table->unsignedBigInteger('action_id')->nullable();

            // Tracking
            $table->date('published_at')->nullable();
            $table->date('last_updated_at')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->foreign('brand_id')
                ->references('id')
                ->on('brands')
                ->onDelete('cascade');

            $table->foreign('action_id')
                ->references('id')
                ->on('ai_actions')
                ->onDelete('set null');

            $table->index(['brand_id', 'status']);
            $table->index(['brand_id', 'published_at']);
            $table->index('slug');
            $table->index('views');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};