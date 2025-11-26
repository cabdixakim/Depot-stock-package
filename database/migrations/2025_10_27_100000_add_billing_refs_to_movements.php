<?php

// database/migrations/2025_10_27_110000_add_billing_refs.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // OFFLOADS: link to invoice when billed
        if (Schema::hasTable('offloads')) {
            Schema::table('offloads', function (Blueprint $t) {
                if (!Schema::hasColumn('offloads', 'billed_invoice_id')) {
                    $t->unsignedBigInteger('billed_invoice_id')->nullable()->index()->after('id');
                    $t->timestamp('billed_at')->nullable()->after('billed_invoice_id');
                    $t->foreign('billed_invoice_id')->references('id')->on('invoices')->nullOnDelete();
                }
            });
        }

        // ADJUSTMENTS: direction + billable flag + invoice refs
        if (Schema::hasTable('adjustments')) {
            Schema::table('adjustments', function (Blueprint $t) {
                if (!Schema::hasColumn('adjustments', 'type')) {
                    $t->enum('type', ['positive','negative'])->default('positive')->after('id');
                }
                if (!Schema::hasColumn('adjustments', 'is_billable')) {
                    $t->boolean('is_billable')->default(false)->after('type');
                }
                if (!Schema::hasColumn('adjustments', 'reason')) {
                    $t->string('reason', 190)->nullable()->after('is_billable');
                }
                if (!Schema::hasColumn('adjustments', 'billed_invoice_id')) {
                    $t->unsignedBigInteger('billed_invoice_id')->nullable()->index()->after('reason');
                    $t->timestamp('billed_at')->nullable()->after('billed_invoice_id');
                    $t->foreign('billed_invoice_id')->references('id')->on('invoices')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('offloads')) {
            Schema::table('offloads', function (Blueprint $t) {
                if (Schema::hasColumn('offloads', 'billed_invoice_id')) {
                    $t->dropForeign(['billed_invoice_id']);
                    $t->dropColumn(['billed_invoice_id','billed_at']);
                }
            });
        }
        if (Schema::hasTable('adjustments')) {
            Schema::table('adjustments', function (Blueprint $t) {
                $cols = ['billed_invoice_id','billed_at','is_billable','type','reason'];
                foreach ($cols as $c) {
                    if (Schema::hasColumn('adjustments', $c)) $t->dropColumn($c);
                }
            });
        }
    }
};