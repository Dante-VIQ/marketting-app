<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_base', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id');
            $table->string('key');
            $table->string('category')->default('general'); // brand_voice, services, pricing, faqs, case_studies
            $table->longText('content');
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('brand_id')
                ->references('id')
                ->on('brands')
                ->onDelete('cascade');

            $table->unique(['brand_id', 'key']);
            $table->index(['brand_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_base');
    }
};