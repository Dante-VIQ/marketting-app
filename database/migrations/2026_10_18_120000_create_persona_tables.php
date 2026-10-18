<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('personas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);                    // "Budget-conscious first-time safari planner"
            $table->string('role_title', 120)->nullable();
            $table->text('summary')->nullable();
            $table->json('goals')->nullable();             // jobs-to-be-done
            $table->json('triggers')->nullable();          // what makes them start looking
            $table->json('pains')->nullable();             // ranked, most painful first
            $table->json('objections')->nullable();
            $table->json('motivations')->nullable();       // psychographics: values, attitudes, motivations
            $table->json('channels')->nullable();          // where they look for information
            $table->string('buying_role', 40)->nullable(); // decision_maker | influencer | user | gatekeeper
            $table->string('quote', 300)->nullable();      // in their words
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('persona_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained('personas')->cascadeOnDelete();
            $table->string('kind', 16);                    // interview | survey | support | lead_data | analytics | listening | other
            $table->string('note', 500);
            $table->date('observed_on')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('persona_evidence');
        Schema::dropIfExists('personas');
    }
};
