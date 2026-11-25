<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // One nullable column per criterion; null means "any". Mirrors
            // App\Data\ListingCriteria.
            $table->string('property_type')->nullable();
            $table->unsignedInteger('max_price')->nullable();
            $table->unsignedTinyInteger('min_bedrooms')->nullable();
            $table->string('region')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_searches');
    }
};
