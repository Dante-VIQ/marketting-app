<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Affiliate Offers
        Schema::create('affiliate_offers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id');
            $table->string('network'); // travel_payouts, bonusarrive, awin
            $table->string('offer_id')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->nullable(); // hotels, tours, flights, packages
            $table->string('destination')->nullable();
            $table->string('commission_type')->nullable(); // percentage, fixed
            $table->decimal('commission_value', 10, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->decimal('price', 10, 2)->nullable();
            $table->string('url')->nullable();
            $table->string('image_url')->nullable();
            $table->json('keywords')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('brand_id')
                ->references('id')
                ->on('brands')
                ->onDelete('cascade');

            $table->index(['brand_id', 'network']);
            $table->index(['brand_id', 'category']);
            $table->index(['brand_id', 'destination']);
            $table->index('offer_id');
        });

        // Affiliate Data (Daily Performance)
        Schema::create('affiliate_data', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id');
            $table->string('network'); // travel_payouts, bonusarrive, awin
            $table->date('date');
            $table->integer('clicks')->default(0);
            $table->integer('bookings')->default(0);
            $table->decimal('commission_earned', 15, 2)->default(0);
            $table->decimal('revenue_generated', 15, 2)->default(0);
            $table->decimal('conversion_rate', 8, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('brand_id')
                ->references('id')
                ->on('brands')
                ->onDelete('cascade');

            $table->unique(['brand_id', 'network', 'date']);
            $table->index(['brand_id', 'network']);
            $table->index('date');
        });

        // Blog Affiliate Placements
        Schema::create('blog_affiliate_placements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('blog_post_id');
            $table->unsignedBigInteger('affiliate_offer_id');
            $table->string('placement_type'); // in_content, sidebar, banner, cta
            $table->string('anchor_text')->nullable();
            $table->string('url')->nullable();
            $table->integer('clicks')->default(0);
            $table->integer('bookings')->default(0);
            $table->decimal('commission_earned', 15, 2)->default(0);
            $table->decimal('revenue_generated', 15, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('blog_post_id')
                ->references('id')
                ->on('blog_posts')
                ->onDelete('cascade');

            $table->foreign('affiliate_offer_id')
                ->references('id')
                ->on('affiliate_offers')
                ->onDelete('cascade');

            $table->index(['blog_post_id', 'affiliate_offer_id']);
            $table->index('clicks');
        });

        // Affiliate Links
        Schema::create('affiliate_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id');
            $table->string('network');
            $table->string('name');
            $table->string('url');
            $table->string('tracking_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('brand_id')
                ->references('id')
                ->on('brands')
                ->onDelete('cascade');

            $table->index(['brand_id', 'network']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_links');
        Schema::dropIfExists('blog_affiliate_placements');
        Schema::dropIfExists('affiliate_data');
        Schema::dropIfExists('affiliate_offers');
    }
};