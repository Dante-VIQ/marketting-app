<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_actions', function (Blueprint $table) {
            // Human response to an escalation
            // investigate | retry | resolve | snooze | (null = no response yet)
            $table->string('human_response')->nullable()->after('agent_notified_at');

            // Human's notes explaining the response
            $table->text('human_response_notes')->nullable()->after('human_response');

            // When the human responded
            $table->timestamp('human_response_at')->nullable()->after('human_response_notes');

            // For "snooze" responses — when to wake up
            $table->date('snooze_until')->nullable()->after('human_response_at');

            $table->index('human_response');
            $table->index('snooze_until');
        });
    }

    public function down(): void
    {
        Schema::table('ai_actions', function (Blueprint $table) {
            $table->dropIndex(['human_response']);
            $table->dropIndex(['snooze_until']);
            $table->dropColumn([
                'human_response',
                'human_response_notes',
                'human_response_at',
                'snooze_until',
            ]);
        });
    }
};