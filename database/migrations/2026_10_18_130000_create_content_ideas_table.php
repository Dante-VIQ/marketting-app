<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // A planned piece of content. A human posts it; the system never publishes.
        Schema::create('content_ideas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('persona_id')->nullable()->constrained('personas')->nullOnDelete();
            $table->string('title', 200);
            $table->string('topic', 80)->nullable();
            $table->string('platform', 16)->nullable();      // facebook | instagram | linkedin | x | tiktok | youtube | whatsapp | blog | other
            $table->string('format', 16)->nullable();        // short_video | image | carousel | text | article | story | other
            $table->string('source', 16)->default('manual'); // manual | competitor_gap | listening | keyword | persona
            $table->string('source_detail', 300)->nullable();
            $table->text('copy')->nullable();                // draft text to copy-paste
            $table->string('status', 10)->default('idea');   // idea | ready | posted | skipped
            $table->date('planned_for')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->string('post_url', 1000)->nullable();
            // Results, entered by hand or from a platform export. null = not measured (never 0).
            $table->unsignedInteger('impressions')->nullable();
            $table->unsignedInteger('engagements')->nullable();
            $table->unsignedInteger('clicks')->nullable();
            $table->unsignedInteger('shares')->nullable();
            $table->timestamp('measured_at')->nullable();
            $table->timestamps();

            $table->index(['brand_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_ideas');
    }
};
