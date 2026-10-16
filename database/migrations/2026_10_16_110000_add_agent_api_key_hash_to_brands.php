<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            // SHA-256 of a high-entropy random key (see `php artisan agent:issue-key`).
            $table->string('agent_api_key_hash', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropUnique(['agent_api_key_hash']);
            $table->dropColumn('agent_api_key_hash');
        });
    }
};
