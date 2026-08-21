<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_guides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id');
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('destination');
            $table->string('duration')->nullable(); // "3 days", "5 days", etc.
            $table->text('description')->nullable();
            $table->longText('content')->nullable();
            $table->json('itinerary')->nullable(); // day-by-day plan
            $table->json('tour_packages')->nullable(); // linked tour packages
            $table->json('affiliate_offers')->nullable(); // linked affiliate offers
            $table->string('featured_image')->nullable();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->integer('views')->default(0);
            $table->integer('bookings_generated')->default(0);
            $table->decimal('revenue_generated', 15, 2)->default(0);
            $table->date('published_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('brand_id')
                ->references('id')
                ->on('brands')
                ->onDelete('cascade');

            $table->index(['brand_id', 'destination']);
            $table->index(['brand_id', 'status']);
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_guides');
    }
};