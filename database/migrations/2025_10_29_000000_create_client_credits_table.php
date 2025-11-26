<?php

// database/migrations/2025_10_29_000000_create_client_credits_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('client_credits', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('client_id')->index();
            $t->unsignedBigInteger('payment_id')->nullable()->index(); // source payment
            $t->decimal('amount', 18, 2);        // original credit
            $t->decimal('remaining', 18, 2);     // what’s left to apply
            $t->string('currency', 3)->default('USD');
            $t->string('reason')->nullable();    // e.g. "Overpayment INV-202510-0004"
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('client_credits'); }
};