<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_opportunity_trackings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id');

            // stable_key = same problem regardless of day
            $table->string('stable_key', 64)->index();

            // fingerprint = stable_key + day (unique per day)
            $table->string('fingerprint', 64)->unique();
            $table->date('tracked_date')->index();

            $table->string('opportunity_type');
            $table->json('opportunity_data')->nullable();

            // Tracking over time
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->unsignedInteger('times_seen')->default(1);
            $table->unsignedInteger('recurrence_count')->default(1);

            // Processing state
            $table->timestamp('last_processed_at')->nullable();
            $table->string('status')->default('new');
            $table->unsignedBigInteger('action_id')->nullable();

            $table->string('agent_id')->nullable();
            $table->timestamps();

            $table->foreign('brand_id')
                ->references('id')
                ->on('brands')
                ->onDelete('cascade');

            $table->index(['brand_id', 'stable_key']);
            $table->index(['brand_id', 'status']);
            $table->index(['brand_id', 'tracked_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_opportunity_trackings');
    }
};