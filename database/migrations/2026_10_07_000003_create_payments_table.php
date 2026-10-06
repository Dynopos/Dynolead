<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per CHIP purchase (one 30-day period). Card data never touches us.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('plan', 30);
            $table->unsignedInteger('amount_sen');
            $table->string('currency', 3)->default('MYR');
            // created|paid|failed|cancelled|expired|manual
            $table->string('status', 20)->default('created');
            $table->string('chip_purchase_id')->nullable()->unique();
            $table->text('checkout_url')->nullable();
            $table->boolean('is_test')->default(false);
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
