<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('business_type');
            $table->json('areas');
            $table->unsignedSmallInteger('max_candidates')->default(20);
            // pending|searching|filtering|details|scoring|writing|done|failed|budget_exceeded
            $table->string('status', 20)->default('pending');
            // Only place_ids are kept long term (Google terms allow this).
            $table->json('candidate_place_ids')->nullable();
            $table->json('passed_place_ids')->nullable();
            $table->unsignedSmallInteger('found_count')->default(0);
            $table->unsignedSmallInteger('passed_count')->default(0);
            $table->unsignedSmallInteger('lead_count')->default(0);
            $table->unsignedSmallInteger('scored_count')->default(0);
            $table->unsignedSmallInteger('written_count')->default(0);
            $table->unsignedSmallInteger('places_calls')->default(0);
            $table->decimal('estimate_myr', 10, 4)->nullable();
            $table->decimal('actual_cost_myr', 10, 4)->default(0);
            $table->json('rejections')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('searches');
    }
};
