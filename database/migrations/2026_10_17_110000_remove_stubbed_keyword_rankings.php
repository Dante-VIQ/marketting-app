<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The only writer of keyword_rankings used to be a stub inserting four hard-coded
        // keywords with random positions/volumes (page_url '/', no metadata). Real rows
        // synced from Ahrefs always carry metadata.source = 'ahrefs'.
        DB::table('keyword_rankings')->whereNull('metadata')->delete();
    }

    public function down(): void
    {
        // Fake data is not restored.
    }
};
