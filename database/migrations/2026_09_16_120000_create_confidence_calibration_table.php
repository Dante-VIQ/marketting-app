<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('confidence_calibrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id');

            // Bucket: 0.00–0.09, 0.10–0.19, ..., 0.90–1.00 (stored as 0..10)
            $table->tinyInteger('confidence_bucket');

            // Context
            $table->string('opportunity_type')->index();
            $table->string('action_name')->nullable();

            // Stats
            $table->unsignedInteger('total_predictions')->default(0);
            $table->unsignedInteger('successful_predictions')->default(0);
            $table->float('actual_accuracy')->default(0.0);

            // Last update
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamps();

            $table->foreign('brand_id')->references('id')->on('brands')->onDelete('cascade');
            $table->unique(['brand_id', 'confidence_bucket', 'opportunity_type', 'action_name'], 'unique_calibration_bucket');
            $table->index(['brand_id', 'opportunity_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('confidence_calibrations');
    }
};