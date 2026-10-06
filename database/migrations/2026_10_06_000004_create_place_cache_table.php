<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Temporary copy of Google Places content. Purged after PLACES_CACHE_HOURS.
        Schema::create('place_cache', function (Blueprint $table) {
            $table->id();
            $table->string('place_id')->unique();
            $table->json('payload');
            $table->boolean('has_details')->default(false);
            $table->timestamp('fetched_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_cache');
    }
};
