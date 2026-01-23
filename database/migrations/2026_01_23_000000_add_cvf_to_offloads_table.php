<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Defensive: only add column if it doesn't already exist
        if (!Schema::hasColumn('offloads', 'cvf')) {
            Schema::table('offloads', function (Blueprint $table) {
                $table
                    ->decimal('cvf', 10, 6)
                    ->nullable()
                    ->after('delivered_observed_l')
                    ->comment('Correction Volume Factor used to compute delivered @20°C');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Defensive rollback
        if (Schema::hasColumn('offloads', 'cvf')) {
            Schema::table('offloads', function (Blueprint $table) {
                $table->dropColumn('cvf');
            });
        }
    }
};