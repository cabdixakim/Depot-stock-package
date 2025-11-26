<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // OFFLOADS
        if (Schema::hasTable('offloads') && !Schema::hasColumn('offloads', 'created_by_user_id')) {
            Schema::table('offloads', function (Blueprint $table) {
                $table->unsignedBigInteger('created_by_user_id')->nullable()->after('client_id');
                $table->foreign('created_by_user_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }

        // LOADS
        if (Schema::hasTable('loads') && !Schema::hasColumn('loads', 'created_by_user_id')) {
            Schema::table('loads', function (Blueprint $table) {
                $table->unsignedBigInteger('created_by_user_id')->nullable()->after('client_id');
                $table->foreign('created_by_user_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }

        // ADJUSTMENTS
        if (Schema::hasTable('adjustments') && !Schema::hasColumn('adjustments', 'created_by_user_id')) {
            Schema::table('adjustments', function (Blueprint $table) {
                $table->unsignedBigInteger('created_by_user_id')->nullable()->after('client_id');
                $table->foreign('created_by_user_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }

        // INVOICES
        if (Schema::hasTable('invoices') && !Schema::hasColumn('invoices', 'created_by_user_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->unsignedBigInteger('created_by_user_id')->nullable()->after('client_id');
                $table->foreign('created_by_user_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }

        // PAYMENTS
        if (Schema::hasTable('payments') && !Schema::hasColumn('payments', 'created_by_user_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->unsignedBigInteger('created_by_user_id')->nullable()->after('client_id');
                $table->foreign('created_by_user_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('offloads') && Schema::hasColumn('offloads', 'created_by_user_id')) {
            Schema::table('offloads', function (Blueprint $table) {
                $table->dropForeign(['created_by_user_id']);
                $table->dropColumn('created_by_user_id');
            });
        }

        if (Schema::hasTable('loads') && Schema::hasColumn('loads', 'created_by_user_id')) {
            Schema::table('loads', function (Blueprint $table) {
                $table->dropForeign(['created_by_user_id']);
                $table->dropColumn('created_by_user_id');
            });
        }

        if (Schema::hasTable('adjustments') && Schema::hasColumn('adjustments', 'created_by_user_id')) {
            Schema::table('adjustments', function (Blueprint $table) {
                $table->dropForeign(['created_by_user_id']);
                $table->dropColumn('created_by_user_id');
            });
        }

        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'created_by_user_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropForeign(['created_by_user_id']);
                $table->dropColumn('created_by_user_id');
            });
        }

        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'created_by_user_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropForeign(['created_by_user_id']);
                $table->dropColumn('created_by_user_id');
            });
        }
    }
};