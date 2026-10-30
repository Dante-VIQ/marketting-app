<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('user_id');
            $table->string('mode')->default('assistant'); // assistant | onboarding
            $table->json('metadata')->nullable();         // brand snapshot at session start
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();

            $table->foreign('brand_id')->references('id')->on('brands')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->index(['brand_id', 'user_id']);
            $table->index('last_active_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_sessions');
    }
};
