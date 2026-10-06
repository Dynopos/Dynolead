<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Billing model (Bob, Oct 2026):
 *  - free trial: 20 leads or 14 days, whichever comes first;
 *  - one-time activation fee;
 *  - prepaid RM balance, each search charged at actual AI + Places cost plus a markup.
 *
 * workspaces.plan is 'pelanggan' (customer) or 'dalaman' (platform owner, never charged).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->integer('balance_sen')->default(0)->after('plan');
            $table->timestamp('activated_at')->nullable()->after('trial_ends_at');
            $table->string('plan', 30)->default('pelanggan')->change();
        });
        DB::table('workspaces')->where('plan', '!=', 'dalaman')->update(['plan' => 'pelanggan']);

        // Ledger of every balance change. Sum of amount_sen = balance_sen.
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->integer('amount_sen');
            $table->integer('balance_after_sen');
            // topup|usage|admin
            $table->string('reason', 20);
            // Raw cost behind a usage charge (admin view only).
            $table->integer('cost_sen')->nullable();
            $table->foreignId('search_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::table('searches', function (Blueprint $table) {
            $table->boolean('is_trial')->default(false)->after('max_candidates');
            $table->integer('charged_sen')->default(0)->after('is_trial');
        });

        // Usage rows made inside a paid search carry its id: that is what gets billed.
        foreach (['ai_usage', 'places_usage'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('billable_search_id')->nullable()->constrained('searches')->nullOnDelete();
            });
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->renameColumn('plan', 'kind');
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
            $table->renameColumn('kind', 'plan');
        });
        foreach (['ai_usage', 'places_usage'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('billable_search_id');
            });
        }
        Schema::table('searches', function (Blueprint $table) {
            $table->dropColumn(['is_trial', 'charged_sen']);
        });
        Schema::dropIfExists('wallet_transactions');
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn(['balance_sen', 'activated_at']);
        });
    }
};
