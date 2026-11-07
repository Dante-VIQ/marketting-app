<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('content_ideas', function (Blueprint $table) {
            // A suggested prompt for an image tool. A human makes/chooses the image and pastes its public URL.
            $table->text('image_prompt')->nullable();
            $table->string('image_alt', 300)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('content_ideas', function (Blueprint $table) {
            $table->dropColumn(['image_prompt', 'image_alt']);
        });
    }
};
