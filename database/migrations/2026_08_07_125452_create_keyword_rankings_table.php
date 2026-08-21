<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keyword_rankings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id');
            $table->string('keyword');
            $table->string('page_url')->nullable();
            $table->integer('position')->nullable();
            $table->integer('previous_position')->nullable();
            $table->integer('search_volume')->nullable();
            $table->string('difficulty')->nullable(); // easy, medium, hard
            $table->json('metadata')->nullable();
            $table->date('tracked_date');
            $table->timestamps();

            $table->foreign('brand_id')
                ->references('id')
                ->on('brands')
                ->onDelete('cascade');

            $table->index(['brand_id', 'keyword']);
            $table->index(['brand_id', 'tracked_date']);
            $table->unique(['brand_id', 'keyword', 'tracked_date'], 'keyword_rankings_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keyword_rankings');
    }
};