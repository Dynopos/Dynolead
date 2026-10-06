<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tables whose rows belong to one workspace. place_cache stays shared (temporary Google data). */
    private array $tables = [
        'products', 'searches', 'leads', 'suppressions', 'contacts_log', 'ai_usage', 'places_usage', 'settings',
    ];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('workspace_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            });
        }

        // Product slugs and setting keys are unique per workspace, not globally.
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->unique(['workspace_id', 'slug']);
        });
        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->unique(['workspace_id', 'key']);
        });

        $this->backfill();
    }

    /** Fasa 0 data (if any) moves into one workspace owned by the first account. */
    private function backfill(): void
    {
        $hasData = collect($this->tables)
            ->reject(fn ($t) => $t === 'settings')
            ->contains(fn ($t) => DB::table($t)->exists());

        if (! $hasData) {
            return;
        }

        $id = DB::table('workspaces')->insertGetId([
            'name' => 'DynoPOS Technologies',
            'slug' => 'dynopos-technologies',
            'sender_name' => 'Bob',
            'plan' => 'dalaman',
            'onboarded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($this->tables as $t) {
            DB::table($t)->whereNull('workspace_id')->update(['workspace_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique(['workspace_id', 'key']);
            $table->unique(['key']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['workspace_id', 'slug']);
            $table->unique(['slug']);
        });

        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('workspace_id');
            });
        }
    }
};
