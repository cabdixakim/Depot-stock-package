<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // If the table doesn't exist in this environment, don't crash – just skip.
        // Fresh installs should already have unit_price in the create migration.
        if (! Schema::hasTable('depot_pool_entries')) {
            return;
        }

        Schema::table('depot_pool_entries', function (Blueprint $table) {
            // Only add it if it's not already there
            if (! Schema::hasColumn('depot_pool_entries', 'unit_price')) {
                $table->decimal('unit_price', 15, 4)
                      ->nullable()
                      ->after('volume_20_l');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('depot_pool_entries')) {
            return;
        }

        Schema::table('depot_pool_entries', function (Blueprint $table) {
            if (Schema::hasColumn('depot_pool_entries', 'unit_price')) {
                $table->dropColumn('unit_price');
            }
        });
    }
};