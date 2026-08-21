<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            BrandSeeder::class,
            KnowledgeBaseSeeder::class,
            DomainRoutingRulesSeeder::class,
            GuardianPoliciesSeeder::class,
        ]);
    }
}