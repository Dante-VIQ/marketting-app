<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Money actually earned, typed in by a person from the real affiliate/partner statements. Never estimated by the system.
        Schema::create('revenue_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id');
            $table->date('month');                    // first day of the month the money relates to
            $table->string('source', 60);             // e.g. GetYourGuide, Awin, Orange Adventures
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3);
            $table->string('note', 200)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['brand_id', 'month']);
            $table->foreign('brand_id')->references('id')->on('brands')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_entries');
    }
};
