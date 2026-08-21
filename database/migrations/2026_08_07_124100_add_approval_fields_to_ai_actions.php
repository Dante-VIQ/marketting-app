<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_actions', function (Blueprint $table) {
            // Add approval tracking
            $table->timestamp('approved_at')->nullable()->change();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->text('review_notes')->nullable();

            // Add foreign key for reviewed_by
            $table->foreign('reviewed_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('ai_actions', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn([
                'rejected_at',
                'reviewed_at',
                'reviewed_by',
                'review_notes',
            ]);
        });
    }
};