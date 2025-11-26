<?php

// database/migrations/2025_10_27_101000_update_invoices_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('invoices', function (Blueprint $t) {
            // Core links & identity
            if (!Schema::hasColumn('invoices', 'client_id')) {
                $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            }
            if (!Schema::hasColumn('invoices', 'number')) {
                $t->string('number')->unique();
            }
            if (!Schema::hasColumn('invoices', 'date')) {
                $t->date('date')->index();
            }
            if (!Schema::hasColumn('invoices', 'due_date')) {
                $t->date('due_date')->nullable()->index();
            }

            // Status & money
            if (!Schema::hasColumn('invoices', 'status')) {
                $t->enum('status', ['draft','issued','partial','paid','void'])->default('draft')->index();
            }
            if (!Schema::hasColumn('invoices', 'currency')) {
                $t->string('currency', 3)->default('USD');
            }
            if (!Schema::hasColumn('invoices', 'subtotal')) {
                $t->decimal('subtotal', 18, 3)->default(0);
            }
            if (!Schema::hasColumn('invoices', 'tax_total')) {
                $t->decimal('tax_total', 18, 3)->default(0);
            }
            if (!Schema::hasColumn('invoices', 'total')) {
                $t->decimal('total', 18, 3)->default(0)->index();
            }
            if (!Schema::hasColumn('invoices', 'paid_total')) {
                $t->decimal('paid_total', 18, 3)->default(0);
            }

            // Optional metadata
            if (!Schema::hasColumn('invoices', 'notes')) {
                $t->text('notes')->nullable();
            }
            if (!Schema::hasColumn('invoices', 'terms')) {
                $t->text('terms')->nullable();
            }
        });
    }

    public function down(): void {
        Schema::table('invoices', function (Blueprint $t) {
            // Only drop what we added (safe)
            foreach (['number','date','due_date','status','currency','subtotal','tax_total','total','paid_total','notes','terms'] as $col) {
                if (Schema::hasColumn('invoices', $col)) $t->dropColumn($col);
            }
            if (Schema::hasColumn('invoices', 'client_id')) $t->dropConstrainedForeignId('client_id');
        });
    }
};