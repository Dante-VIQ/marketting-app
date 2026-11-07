<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('content_ideas', function (Blueprint $table) {
            // What the post did on the website (from GA4, via the post's UTM tag). Null = not measured, never zero by default.
            $table->unsignedInteger('site_sessions')->nullable();
            $table->unsignedInteger('site_key_events')->nullable();
            $table->timestamp('site_measured_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('content_ideas', function (Blueprint $table) {
            $table->dropColumn(['site_sessions', 'site_key_events', 'site_measured_at']);
        });
    }
};
