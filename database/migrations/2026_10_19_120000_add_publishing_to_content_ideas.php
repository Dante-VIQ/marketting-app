<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('content_ideas', function (Blueprint $table) {
            $table->string('publish_mode', 8)->default('manual')->after('status');   // manual | auto
            $table->timestamp('scheduled_at')->nullable()->after('planned_for');
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();                    // a human must approve every auto post
            $table->string('remote_post_id', 80)->nullable()->index();               // Facebook post id / Instagram media id
            $table->string('media_url', 1000)->nullable();                           // public image URL (required for Instagram)
            $table->string('link_url', 1000)->nullable();
            $table->text('publish_error')->nullable();
            $table->unsignedInteger('reach')->nullable()->after('impressions');
        });
    }

    public function down(): void
    {
        Schema::table('content_ideas', function (Blueprint $table) {
            $table->dropIndex(['remote_post_id']);
            $table->dropColumn(['publish_mode', 'scheduled_at', 'approved_at', 'approved_by', 'remote_post_id', 'media_url', 'link_url', 'publish_error', 'reach']);
        });
    }
};
