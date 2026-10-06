<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pay per search: prepaid credits replace monthly plans.
 * workspaces.plan is now 'kredit' (normal customer) or 'dalaman' (platform owner, no charge).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->unsignedInteger('credits')->default(0)->after('plan');
            $table->string('plan', 30)->default('kredit')->change();
        });
        DB::table('workspaces')->where('plan', '!=', 'dalaman')->update(['plan' => 'kredit']);

        // Ledger: every change to a balance, with the reason. Sum of amount = balance.
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->integer('amount');
            $table->unsignedInteger('balance_after');
            // signup_bonus|purchase|search|refund|admin
            $table->string('reason', 20);
            $table->foreignId('search_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::table('searches', function (Blueprint $table) {
            $table->unsignedSmallInteger('credits_charged')->default(0)->after('max_candidates');
            $table->timestamp('credits_refunded_at')->nullable()->after('credits_charged');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->renameColumn('plan', 'pack');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedInteger('credits')->default(0)->after('pack');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->unsignedTinyInteger('regenerate_count')->default(0)->after('prompt_version');
            $table->unsignedTinyInteger('followup_count')->default(0)->after('followup_message');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['regenerate_count', 'followup_count']);
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('credits');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->renameColumn('pack', 'plan');
        });
        Schema::table('searches', function (Blueprint $table) {
            $table->dropColumn(['credits_charged', 'credits_refunded_at']);
        });
        Schema::dropIfExists('credit_transactions');
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('credits');
        });
    }
};
