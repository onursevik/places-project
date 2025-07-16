<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('places', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('website')->nullable();
            $table->json('emails')->nullable();
            $table->json('social_links')->nullable();
            $table->json('types')->nullable();
            $table->json('opening_hours')->nullable();
            $table->string('lat')->nullable();
            $table->string('lng')->nullable();
            $table->string('rating')->nullable();
            $table->string('business_status')->nullable();
            $table->enum('status', ['0', '1', '2'])->default('2');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('places');
    }
};
