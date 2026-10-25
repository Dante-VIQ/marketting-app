<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('competitor_suggestions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id');
            $table->string('domain');
            $table->string('name')->nullable();
            $table->json('sources');                          // ai | search | your_notes
            $table->string('classification', 20);             // bookings | content | both | unclear | big_platform
            $table->unsignedTinyInteger('score')->nullable(); // % of the brand's topic words found on their site; null = not measured
            $table->json('shared_terms')->nullable();
            $table->unsignedTinyInteger('query_hits')->default(0); // how many of our searches returned them
            $table->text('reason')->nullable();               // plain-language why, shown to the human
            $table->string('homepage_title')->nullable();
            $table->string('status', 12)->default('suggested'); // suggested | accepted | dismissed
            $table->timestamps();

            $table->unique(['brand_id', 'domain']);
            $table->index(['brand_id', 'status']);
            $table->foreign('brand_id')->references('id')->on('brands')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_suggestions');
    }
};
