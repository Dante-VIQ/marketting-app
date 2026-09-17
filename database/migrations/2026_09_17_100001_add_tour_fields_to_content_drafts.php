<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_drafts', function (Blueprint $table) {
            // $table->unsignedBigInteger('tour_package_id')->nullable()->after('id');
            // $table->string('affiliate_url')->nullable()->after('tour_package_id');
            // $table->float('tour_match_score')->nullable()->after('affiliate_url');
            // $table->json('tour_match_candidates')->nullable()->after('tour_match_score');
            // $table->boolean('tour_enforced')->default(false)->after('tour_match_candidates');

            $table->foreign('tour_package_id')
                ->references('id')
                ->on('tour_packages')
                ->onDelete('set null');

            $table->index('tour_enforced');
        });
    }

    public function down(): void
    {
        Schema::table('content_drafts', function (Blueprint $table) {
            $table->dropForeign(['tour_package_id']);
            $table->dropColumn([
                'tour_package_id',
                'affiliate_url',
                'tour_match_score',
                'tour_match_candidates',
                'tour_enforced',
            ]);
        });
    }
};