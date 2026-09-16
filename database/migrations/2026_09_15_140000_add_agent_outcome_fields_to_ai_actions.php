<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_actions', function (Blueprint $table) {
            // When did the agent acknowledge this outcome?
            $table->timestamp('agent_notified_at')->nullable()->after('reviewed_at');

            // Retry authorization
            // none         = no retry
            // authorized   = human authorized retry with guidance
            // held         = approved but wait for human to unhold
            $table->string('retry_status')->default('none')->after('agent_notified_at');

            // Human's guidance for retry
            $table->text('expected_retry_approach')->nullable()->after('retry_status');

            // Link back to the opportunity that created this action
            $table->string('opportunity_fingerprint', 64)->nullable()->after('expected_retry_approach');
            $table->string('opportunity_stable_key', 64)->nullable()->after('opportunity_fingerprint');

            // Where did this action come from?
            // original = first attempt at this problem
            // retry    = retry authorized by human
            $table->string('origin')->default('original')->after('opportunity_stable_key');

            $table->index('retry_status');
            $table->index('opportunity_stable_key');
            $table->index('agent_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('ai_actions', function (Blueprint $table) {
            $table->dropIndex(['retry_status']);
            $table->dropIndex(['opportunity_stable_key']);
            $table->dropIndex(['agent_notified_at']);
            $table->dropColumn([
                'agent_notified_at',
                'retry_status',
                'expected_retry_approach',
                'opportunity_fingerprint',
                'opportunity_stable_key',
                'origin',
            ]);
        });
    }
};