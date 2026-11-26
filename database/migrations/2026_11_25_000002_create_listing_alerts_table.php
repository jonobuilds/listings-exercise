<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();

            // The search that matched first. Deleting the search keeps the
            // alert: the user was still told about the listing.
            $table->foreignId('saved_search_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            // One alert per listing per user, however many of their searches
            // match and however often the listing goes live. Enforced here so
            // it holds under concurrent saves, not just in application code.
            // The user_id prefix also serves the alerts page lookup.
            $table->unique(['user_id', 'listing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_alerts');
    }
};
