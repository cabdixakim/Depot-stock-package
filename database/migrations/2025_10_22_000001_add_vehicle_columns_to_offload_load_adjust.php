<?php

// database/migrations/2025_10_22_000001_add_vehicle_columns_to_offload_load_adjust.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('offloads', function (Blueprint $table) {
            $table->string('truck_plate', 50)->nullable()->after('client_id');
            $table->string('trailer_plate', 50)->nullable()->after('truck_plate');
        });

        Schema::table('loads', function (Blueprint $table) {
            $table->string('truck_plate', 50)->nullable()->after('client_id');
            $table->string('trailer_plate', 50)->nullable()->after('truck_plate');
        });

        Schema::table('adjustments', function (Blueprint $table) {
            $table->string('truck_plate', 50)->nullable()->after('client_id');
            $table->string('trailer_plate', 50)->nullable()->after('truck_plate');
        });
    }

    public function down(): void
    {
        Schema::table('offloads', function (Blueprint $table) {
            $table->dropColumn(['truck_plate','trailer_plate']);
        });
        Schema::table('loads', function (Blueprint $table) {
            $table->dropColumn(['truck_plate','trailer_plate']);
        });
        Schema::table('adjustments', function (Blueprint $table) {
            $table->dropColumn(['truck_plate','trailer_plate']);
        });
    }
};
