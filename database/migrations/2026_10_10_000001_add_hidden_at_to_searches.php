<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Padam" on the search screen hides a finished search from the list. The row stays,
 * so costs, charges and leads keep their link to it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('searches', function (Blueprint $table) {
            $table->timestamp('hidden_at')->nullable()->after('finished_at');
        });
    }

    public function down(): void
    {
        Schema::table('searches', function (Blueprint $table) {
            $table->dropColumn('hidden_at');
        });
    }
};
