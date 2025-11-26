<?php

// database/migrations/2025_10_27_103000_update_payments_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('payments', function (Blueprint $t) {
            if (!Schema::hasColumn('payments','invoice_id')) {
                $t->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete()->index();
            }
            if (!Schema::hasColumn('payments','client_id')) {
                $t->foreignId('client_id')->nullable()->constrained()->nullOnDelete()->index();
            }
            if (!Schema::hasColumn('payments','date')) {
                $t->date('date')->index();
            }
            if (!Schema::hasColumn('payments','amount')) {
                $t->decimal('amount', 18, 3);
            }
            if (!Schema::hasColumn('payments','mode')) {
                $t->string('mode', 40)->nullable(); // cash/bank/mobile
            }
            if (!Schema::hasColumn('payments','reference')) {
                $t->string('reference', 120)->nullable(); // receipt/bank ref
            }
            if (!Schema::hasColumn('payments','currency')) {
                $t->string('currency', 3)->default('USD');
            }
            if (!Schema::hasColumn('payments','notes')) {
                $t->text('notes')->nullable();
            }
        });
    }
    public function down(): void {
        Schema::table('payments', function (Blueprint $t) {
            foreach (['date','amount','mode','reference','currency','notes'] as $col) {
                if (Schema::hasColumn('payments', $col)) $t->dropColumn($col);
            }
            if (Schema::hasColumn('payments','invoice_id')) $t->dropConstrainedForeignId('invoice_id');
            if (Schema::hasColumn('payments','client_id'))  $t->dropConstrainedForeignId('client_id');
        });
    }
};