<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Add a nullable per-litre rate to invoices
    public function up(): void {
        Schema::table('invoices', function (Blueprint $t) {
            if (!Schema::hasColumn('invoices', 'rate_per_litre')) {
                // 10,4 is plenty: e.g. 2.4599 USD/L
                $t->decimal('rate_per_litre', 10, 4)->nullable()->after('currency');
            }
        });
    }

    // Rollback
    public function down(): void {
        Schema::table('invoices', function (Blueprint $t) {
            if (Schema::hasColumn('invoices', 'rate_per_litre')) {
                $t->dropColumn('rate_per_litre');
            }
        });
    }
};