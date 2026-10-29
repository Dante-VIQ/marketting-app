<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add 'travel' to the domain_type ENUM on the brands table.
     *
     * Laravel's Schema::table() cannot modify ENUMs portably, so we use a
     * raw ALTER TABLE statement instead.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE `brands`
            MODIFY COLUMN `domain_type`
                ENUM('marketing','healthcare','education','youth','travel','general')
                NOT NULL DEFAULT 'general'
        ");
    }

    public function down(): void
    {
        // First migrate any 'travel' rows back to 'general' so the ENUM
        // shrink doesn't truncate data.
        DB::table('brands')
            ->where('domain_type', 'travel')
            ->update(['domain_type' => 'general']);

        DB::statement("
            ALTER TABLE `brands`
            MODIFY COLUMN `domain_type`
                ENUM('marketing','healthcare','education','youth','general')
                NOT NULL DEFAULT 'general'
        ");
    }
};
