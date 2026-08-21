<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_health', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->string('component'); // ai_provider, database, cache, queue, api
            $table->string('status'); // healthy, degraded, down
            $table->float('response_time_ms')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->foreign('brand_id')
                ->references('id')
                ->on('brands')
                ->onDelete('cascade');

            $table->index(['brand_id', 'component']);
            $table->index('checked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_health');
    }
};