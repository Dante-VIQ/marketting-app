<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_task_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('task_name')->unique();
            $table->string('display_name');
            $table->string('frequency'); // daily, hourly, every_minute, every_five_minutes, every_fifteen_minutes
            $table->string('scheduled_time')->nullable(); // 05:30, 06:00, etc.
            $table->string('status')->default('idle'); // idle, running, success, failed
            $table->text('last_output')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->integer('success_count')->default(0);
            $table->integer('failure_count')->default(0);
            $table->integer('average_duration_ms')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_task_statuses');
    }
};