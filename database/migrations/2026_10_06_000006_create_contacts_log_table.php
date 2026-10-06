<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Used for the "one shop, one product per 30 days" rule.
        Schema::create('contacts_log', function (Blueprint $table) {
            $table->id();
            $table->string('place_id')->index();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('contacted_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts_log');
    }
};
