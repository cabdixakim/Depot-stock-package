<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // One base allowance entry per offload
        Schema::table('depot_pool_entries', function (Blueprint $t) {
            // If you already have this index, it will be skipped by most DBs.
            $t->unique(['ref_type', 'ref_id'], 'dpe_ref_type_ref_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('depot_pool_entries', function (Blueprint $t) {
            $t->dropUnique('dpe_ref_type_ref_id_unique');
        });
    }
};