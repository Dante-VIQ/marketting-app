<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revenue_leaks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id');
            $table->string('page_url')->nullable();
            $table->string('campaign_name')->nullable();
            $table->string('source'); // analytics, funnel, conversion
            $table->decimal('estimated_loss', 15, 2);
            $table->decimal('traffic_loss', 15, 2)->nullable();
            $table->decimal('conversion_loss', 15, 2)->nullable();
            $table->string('opportunity_description');
            $table->string('status')->default('open'); // open, in_progress, resolved
            $table->date('detected_date');
            $table->date('resolved_date')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('brand_id')
                ->references('id')
                ->on('brands')
                ->onDelete('cascade');

            $table->index(['brand_id', 'status', 'detected_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_leaks');
    }
};