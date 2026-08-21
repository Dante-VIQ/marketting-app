<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_issues', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id');
            $table->string('page_url');
            $table->enum('type', [
                'broken_link',
                'missing_meta',
                'slow_page',
                'keyword_cannibalization',
                'duplicate_content',
                'thin_content',
                'missing_alt_text',
                'no_internal_links'
            ]);
            $table->string('severity')->default('medium'); // low, medium, high, critical
            $table->text('description');
            $table->text('recommendation');
            $table->json('metadata')->nullable();
            $table->enum('status', ['open', 'in_progress', 'resolved'])->default('open');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('brand_id')
                ->references('id')
                ->on('brands')
                ->onDelete('cascade');

            $table->index(['brand_id', 'status']);
            $table->index(['brand_id', 'type']);
            $table->index('page_url');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_issues');
    }
};