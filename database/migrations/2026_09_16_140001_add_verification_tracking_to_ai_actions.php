<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_actions', function (Blueprint $table) {
            // Which verification phases have run
            $table->boolean('verified_immediate')->default(false);
            $table->boolean('verified_hour_1')->default(false);
            $table->boolean('verified_day_1')->default(false);

            // Overall verdict
            $table->string('verification_status')->default('pending');
            // pending, verified, failed, rolled_back

            // Metrics at execution time (snapshot for deltas)
            $table->json('metrics_at_execution')->nullable();

            // Scheduled verification timing
            $table->timestamp('verify_at_hour_1')->nullable();
            $table->timestamp('verify_at_day_1')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ai_actions', function (Blueprint $table) {
            $table->dropColumn([
                'verified_immediate',
                'verified_hour_1',
                'verified_day_1',
                'verification_status',
                'metrics_at_execution',
                'verify_at_hour_1',
                'verify_at_day_1',
            ]);
        });
    }
};