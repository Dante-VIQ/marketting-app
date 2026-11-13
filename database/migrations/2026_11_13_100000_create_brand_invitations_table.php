<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_invitations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('invited_by');
            $table->string('email');
            $table->string('role', 30)->default('editor'); // owner|admin|editor|viewer
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->foreign('brand_id')->references('id')->on('brands')->onDelete('cascade');
            $table->foreign('invited_by')->references('id')->on('users')->onDelete('cascade');

            // One pending invite per email+brand
            $table->unique(['brand_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_invitations');
    }
};
