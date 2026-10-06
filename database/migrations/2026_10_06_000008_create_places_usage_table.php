<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per Google Places API call, for the Kos page.
        Schema::create('places_usage', function (Blueprint $table) {
            $table->id();
            // text_search|details|details_display
            $table->string('sku', 30);
            $table->string('place_id')->nullable();
            $table->decimal('cost_estimate', 10, 6)->default(0);
            $table->foreignId('search_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('places_usage');
    }
};
