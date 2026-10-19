<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ad performance as Meta reports it, one row per (breakdown, dimension values, period).
        Schema::create('ad_insight_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('ad_account_id', 40);
            $table->string('breakdown', 40);                  // total | campaign | age_gender | country | placement
            $table->string('dim1', 80)->nullable();           // age | country | publisher_platform | campaign id
            $table->string('dim2', 80)->nullable();           // gender | platform_position
            $table->string('label', 255)->nullable();         // campaign name
            $table->date('date_start');
            $table->date('date_stop');
            // Unknown is null, never 0.
            $table->unsignedBigInteger('impressions')->nullable();
            $table->unsignedBigInteger('reach')->nullable();
            $table->unsignedBigInteger('clicks')->nullable();
            $table->decimal('spend', 14, 2)->nullable();
            $table->unsignedInteger('results')->nullable();   // leads / link clicks reported as actions
            $table->string('currency', 8)->nullable();
            $table->char('row_hash', 40);
            $table->timestamps();

            $table->unique(['brand_id', 'row_hash']);
            $table->index(['brand_id', 'breakdown', 'date_stop']);
        });

        // What real people wrote under the brand's own posts. Customer voice for personas and listening.
        Schema::create('audience_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 12);                   // facebook | instagram
            $table->string('remote_id', 80);
            $table->string('post_remote_id', 80)->nullable();
            $table->text('body');
            $table->unsignedInteger('likes')->nullable();
            $table->string('sentiment', 10)->nullable();      // positive | neutral | negative (heuristic)
            $table->boolean('is_question')->default(false);
            $table->timestamp('commented_at')->nullable();
            $table->timestamps();

            $table->unique(['brand_id', 'platform', 'remote_id']);
            $table->index(['brand_id', 'commented_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audience_comments');
        Schema::dropIfExists('ad_insight_rows');
    }
};
