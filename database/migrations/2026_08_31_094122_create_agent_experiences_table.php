<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_experiences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id');
            $table->string('action_name');
            $table->string('opportunity_type');
            $table->string('severity');
            $table->json('context')->nullable();
            $table->json('decision')->nullable();
            $table->json('outcome')->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->boolean('was_autonomous')->default(false);
            $table->boolean('was_successful')->default(false);
            $table->decimal('improvement_percentage', 8, 2)->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->text('human_feedback')->nullable();
            $table->string('status')->default('recorded'); // recorded, verified, failed
            $table->timestamps();

            $table->foreign('brand_id')
                ->references('id')
                ->on('brands')
                ->onDelete('cascade');

            $table->index(['brand_id', 'opportunity_type', 'severity']);
            $table->index(['brand_id', 'was_successful']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_experiences');
    }
};