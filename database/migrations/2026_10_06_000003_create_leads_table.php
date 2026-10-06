<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('place_id');
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('search_id')->nullable()->constrained()->nullOnDelete();
            // baru|dihantar|reply|deal|tolak|tak_sesuai
            $table->string('status', 20)->default('baru');
            // Search terms typed by the user (app data, not Google content).
            $table->string('business_type')->nullable();
            $table->string('area')->nullable();
            // AI results (app's own text).
            $table->unsignedTinyInteger('fit')->nullable();
            $table->text('reason')->nullable();
            $table->text('hook')->nullable();
            $table->text('gap')->nullable();
            $table->text('flag')->nullable();
            $table->text('message')->nullable();
            $table->boolean('needs_review')->default(false);
            $table->string('review_note')->nullable();
            $table->string('score_prompt_version', 40)->nullable();
            $table->string('prompt_version', 40)->nullable();
            $table->text('followup_message')->nullable();
            $table->timestamp('contacted_at')->nullable();
            $table->timestamp('next_followup_at')->nullable();
            $table->timestamp('status_changed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['place_id', 'product_id']);
            $table->index(['status', 'next_followup_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
