<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('platform', 32)->nullable()->after('type');       // 'meta', ...
            $table->string('external_id', 64)->nullable()->after('platform'); // id on that platform
            $table->unique(['platform', 'external_id']);
        });

        Schema::table('ai_actions', function (Blueprint $table) {
            $table->unsignedBigInteger('campaign_id')->nullable()->index();
            $table->string('ad_operation', 24)->nullable();   // pause | resume | set_budget
            $table->json('ad_params')->nullable();
            $table->json('reversal_data')->nullable();        // live state captured just before acting
            $table->timestamp('reversed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ai_actions', function (Blueprint $table) {
            $table->dropColumn(['campaign_id', 'ad_operation', 'ad_params', 'reversal_data', 'reversed_at']);
        });
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropUnique(['platform', 'external_id']);
            $table->dropColumn(['platform', 'external_id']);
        });
    }
};
