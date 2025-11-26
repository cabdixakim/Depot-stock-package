<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('depots') && !Schema::hasColumn('depots', 'status')) {
            Schema::table('depots', function (Blueprint $table) {
                $table->string('status', 20)->default('active')->after('location');
            });
        }

        if (Schema::hasTable('tanks') && !Schema::hasColumn('tanks', 'status')) {
            Schema::table('tanks', function (Blueprint $table) {
                $table->string('status', 20)->default('active')->after('capacity_l');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('depots') && Schema::hasColumn('depots', 'status')) {
            Schema::table('depots', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }

        if (Schema::hasTable('tanks') && Schema::hasColumn('tanks', 'status')) {
            Schema::table('tanks', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};