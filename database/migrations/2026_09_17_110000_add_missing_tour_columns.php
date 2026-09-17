<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_packages', function (Blueprint $table) {
            if (!Schema::hasColumn('tour_packages', 'country')) {
                $table->string('country')->nullable()->after('destination');
            }
            if (!Schema::hasColumn('tour_packages', 'currency')) {
                $table->string('currency', 3)->default('USD')->after('price');
            }
            if (!Schema::hasColumn('tour_packages', 'keywords')) {
                $table->json('keywords')->nullable()->after('inclusions');
            }
            if (!Schema::hasColumn('tour_packages', 'affiliate_network')) {
                $table->string('affiliate_network')->nullable()->after('keywords');
            }
            if (!Schema::hasColumn('tour_packages', 'affiliate_url')) {
                $table->string('affiliate_url')->nullable()->after('affiliate_network');
            }
            if (!Schema::hasColumn('tour_packages', 'image_url')) {
                $table->string('image_url')->nullable()->after('affiliate_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tour_packages', function (Blueprint $table) {
            foreach (['country', 'currency', 'keywords', 'affiliate_network', 'affiliate_url', 'image_url'] as $col) {
                if (Schema::hasColumn('tour_packages', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};