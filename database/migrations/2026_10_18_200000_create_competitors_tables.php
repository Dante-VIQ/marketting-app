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
            $table->unsignedBigInteger('brand_id');
            $table->string('domain');
            $table->string('source', 16)->default('ahrefs');      // ahrefs | manual
            $table->string('status', 16)->default('tracked');     // tracked | ignored
            $table->decimal('domain_rating', 5, 1)->nullable();
            $table->unsignedInteger('keywords_common')->nullable();
            $table->unsignedInteger('keywords_competitor')->nullable();  // they rank for, we don't
            $table->unsignedInteger('keywords_target')->nullable();      // we rank for, they don't
            $table->decimal('share', 5, 2)->nullable();
            $table->unsignedBigInteger('traffic')->nullable();
            $table->bigInteger('traffic_change')->nullable();
            $table->date('tracked_date')->nullable();
            $table->timestamps();

            $table->foreign('brand_id')->references('id')->on('brands')->onDelete('cascade');
            $table->unique(['brand_id', 'domain']);
        });

        Schema::create('competitor_keywords', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('competitor_id');
            $table->string('keyword');
            $table->unsignedSmallInteger('position')->nullable();
            $table->unsignedInteger('volume')->nullable();
            $table->unsignedTinyInteger('difficulty')->nullable();
            $table->text('url')->nullable();
            $table->unsignedBigInteger('traffic')->nullable();
            $table->boolean('is_gap')->default(false);   // we do not rank well for it
            $table->date('tracked_date');
            $table->timestamps();

            $table->foreign('brand_id')->references('id')->on('brands')->onDelete('cascade');
            $table->foreign('competitor_id')->references('id')->on('competitors')->onDelete('cascade');
            $table->unique(['competitor_id', 'keyword']);
            $table->index(['brand_id', 'is_gap']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_keywords');
        Schema::dropIfExists('competitors');
    }
};
