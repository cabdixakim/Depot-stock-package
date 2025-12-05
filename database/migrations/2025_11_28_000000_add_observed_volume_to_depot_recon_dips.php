<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('depot_recon_dips', function (Blueprint $table) {
            if (! Schema::hasColumn('depot_recon_dips', 'volume_observed_l')) {
                $table->decimal('volume_observed_l', 20, 4)
                    ->nullable()
                    ->after('density_kg_l');
            }
        });
    }

    public function down(): void
    {
        Schema::table('depot_recon_dips', function (Blueprint $table) {
            if (Schema::hasColumn('depot_recon_dips', 'volume_observed_l')) {
                $table->dropColumn('volume_observed_l');
            }
        });
    }
};