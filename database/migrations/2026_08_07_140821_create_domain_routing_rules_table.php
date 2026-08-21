<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_routing_rules', function (Blueprint $table) {
            $table->id();
            $table->string('domain_type');
            $table->string('intent_type'); // analyze, generate, recommend, diagnose
            $table->string('prompt_template_key');
            $table->string('default_model')->nullable();
            $table->json('config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['domain_type', 'intent_type']);
            $table->index('domain_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_routing_rules');
    }
};