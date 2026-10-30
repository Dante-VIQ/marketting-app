<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('session_id');
            $table->enum('role', ['user', 'assistant']);
            $table->longText('content');
            $table->string('action_type')->nullable(); // e.g. get_analytics, get_leads
            $table->json('action_data')->nullable();   // structured data returned by action
            $table->integer('tokens_used')->default(0);
            $table->timestamps();

            $table->foreign('session_id')->references('id')->on('chat_sessions')->onDelete('cascade');
            $table->index(['session_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
