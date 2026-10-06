<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sender_name');
            $table->string('company');
            $table->text('pitch_core');
            // [{"key":"runcit","match":["runcit","grocery_store"],"pitch":"..."}]
            $table->json('pitch_variants')->nullable();
            $table->text('cta');
            $table->json('banned_words')->nullable();
            $table->text('fit_signals')->nullable();
            // {"min_rating":3.5,"min_reviews":10,"require_no_website":false}
            $table->json('filters')->nullable();
            $table->json('default_place_types')->nullable();
            $table->string('contact_info')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
