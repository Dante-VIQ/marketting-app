<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_logs', function (Blueprint $table) {
            $table->id();
            $table->string('job_name');
            $table->string('job_type'); // scheduled, manual
            $table->string('status'); // running, success, failed, skipped
            $table->text('output')->nullable();
            $table->text('error_message')->nullable();
            $table->json('context')->nullable();
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('job_name');
            $table->index('status');
            $table->index(['job_name', 'status']);
            $table->index('created_at');
        });

        Schema::create('schedule_manual_triggers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('job_name');
            $table->string('status'); // queued, running, completed, failed
            $table->text('output')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->index('job_name');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_manual_triggers');
        Schema::dropIfExists('schedule_logs');
    }
};