<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('client_credits', function (Blueprint $table) {
            $table->string('source')->default('overpayment')->after('currency');
            $table->unsignedBigInteger('invoice_id')->nullable()->after('payment_id');
            $table->unsignedBigInteger('created_by')->nullable()->after('invoice_id');
            $table->json('meta')->nullable()->after('created_by');

            $table->index('source');
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::table('client_credits', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropIndex(['invoice_id']);
            $table->dropColumn(['source', 'invoice_id', 'created_by', 'meta']);
        });
    }
};