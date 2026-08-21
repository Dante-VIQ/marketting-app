<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_drafts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('action_id')->nullable();
            $table->string('title');
            $table->enum('type', ['blog', 'social', 'email', 'web_copy', 'newsletter']);
            $table->longText('content');
            $table->text('excerpt')->nullable();
            $table->string('target_keyword')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->json('seo_data')->nullable(); // Keywords, readability score, etc.
            $table->enum('status', ['draft', 'review', 'approved', 'published'])->default('draft');
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('published_url')->nullable();
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

            $table->foreign('reviewed_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index(['brand_id', 'status']);
            $table->index(['brand_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_drafts');
    }
};