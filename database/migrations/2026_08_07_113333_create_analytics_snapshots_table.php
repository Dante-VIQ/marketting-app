<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id');
            $table->date('date');
            $table->string('source'); // ga4, search_console, facebook, linkedin, database
            $table->string('metric'); // visitors, page_views, engagements, leads, conversions
            $table->string('dimension')->nullable(); // page_url, campaign_name, country, etc.
            $table->decimal('value', 15, 2)->default(0);
            $table->decimal('change_wo_w', 8, 2)->nullable(); // Week-over-week percentage change
            $table->decimal('change_mo_m', 8, 2)->nullable(); // Month-over-month percentage change
            $table->json('metadata')->nullable(); // Additional context
            $table->timestamps();

            $table->foreign('brand_id')
                ->references('id')
                ->on('brands')
                ->onDelete('cascade');

            $table->index(['brand_id', 'date', 'source', 'metric']);
            $table->index(['brand_id', 'date']);
            $table->unique(['brand_id', 'date', 'source', 'metric', 'dimension'], 'analytics_snapshot_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_snapshots');
    }
};