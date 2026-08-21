<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // Follow-up tracking
            $table->timestamp('follow_up_sent_at')->nullable();
            $table->timestamp('follow_up_responded_at')->nullable();
            $table->integer('follow_up_count')->default(0);
            $table->json('missing_fields')->nullable(); // Fields that need completion
            $table->json('follow_up_history')->nullable(); // History of follow-ups
            $table->string('follow_up_status')->default('complete'); // complete, pending, waiting, responded
            
            // Response tracking
            $table->text('follow_up_response')->nullable(); // Client's response to follow-up
            $table->json('updated_fields')->nullable(); // Fields updated via follow-up
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'follow_up_sent_at',
                'follow_up_responded_at',
                'follow_up_count',
                'missing_fields',
                'follow_up_history',
                'follow_up_status',
                'follow_up_response',
                'updated_fields',
            ]);
        });
    }
};