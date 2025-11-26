<?php

// database/migrations/xxxx_xx_xx_xxxxxx_add_unit_price_to_depot_pool_entries.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('depot_pool_entries', function (Blueprint $table) {
            $table->decimal('unit_price', 15, 4)
                  ->nullable()
                  ->after('volume_20_l');
        });
    }

    public function down(): void
    {
        Schema::table('depot_pool_entries', function (Blueprint $table) {
            $table->dropColumn('unit_price');
        });
    }
};