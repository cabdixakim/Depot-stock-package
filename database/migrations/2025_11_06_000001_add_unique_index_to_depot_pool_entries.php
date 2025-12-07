<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // If the table somehow doesn't exist, don't crash – just skip
        if (! Schema::hasTable('depot_pool_entries')) {
            return;
        }

        // Extra safety: make sure the columns exist
        if (! Schema::hasColumn('depot_pool_entries', 'ref_type')
            || ! Schema::hasColumn('depot_pool_entries', 'ref_id')) {
            return;
        }

        Schema::table('depot_pool_entries', function (Blueprint $t) {
            $t->unique(['ref_type', 'ref_id'], 'dpe_ref_type_ref_id_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('depot_pool_entries')) {
            return;
        }

        Schema::table('depot_pool_entries', function (Blueprint $t) {
            // Drop by name we created above
            $t->dropUnique('dpe_ref_type_ref_id_unique');
        });
    }
};