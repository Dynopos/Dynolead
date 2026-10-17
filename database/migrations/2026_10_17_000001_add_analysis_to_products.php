<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Business analysis (AI): ideal customers, signals and red flags for a product. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('analysis')->nullable()->after('fit_signals');
            $table->unsignedSmallInteger('analysis_count')->default(0)->after('analysis');
            $table->timestamp('analysed_at')->nullable()->after('analysis_count');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['analysis', 'analysis_count', 'analysed_at']);
        });
    }
};
