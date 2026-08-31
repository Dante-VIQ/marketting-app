<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL doesn't support modifying ENUM directly in some versions
        // We'll use a safer approach: create a new column and migrate data

        Schema::table('content_drafts', function (Blueprint $table) {
            // Temporarily add a new status column
            $table->enum('status_new', [
                'draft',
                'review',
                'revision',
                'approved',
                'published'
            ])->default('draft')->after('status');
        });

        // Copy data from old status to new status
        DB::statement("UPDATE content_drafts SET status_new = status");

        // Drop old status column
        Schema::table('content_drafts', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        // Rename new status column to status
        Schema::table('content_drafts', function (Blueprint $table) {
            $table->renameColumn('status_new', 'status');
        });
    }

    public function down(): void
    {
        // Revert back to original status enum
        Schema::table('content_drafts', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('content_drafts', function (Blueprint $table) {
            $table->enum('status', ['draft', 'approved', 'published'])->default('draft');
        });
    }
};
