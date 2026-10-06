<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Permanent do-not-contact list, across every product.
        Schema::create('suppressions', function (Blueprint $table) {
            $table->id();
            $table->string('place_id')->nullable()->index();
            $table->string('phone', 20)->nullable()->index();
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppressions');
    }
};
