<?php

// database/migrations/2025_10_27_110100_create_invoice_items.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('invoice_id')->index();
            $t->unsignedBigInteger('client_id')->index();

            // link back to the source row
            $t->enum('source_type', ['offload','adjustment']);
            $t->unsignedBigInteger('source_id')->index();

            $t->date('date')->nullable();
            $t->string('description', 190)->nullable();

            // quantities + money
            $t->decimal('litres', 15, 3)->default(0);
            $t->decimal('rate_per_litre', 10, 4)->nullable(); // optional — can be stored on invoice header too
            $t->decimal('amount', 15, 2)->nullable();

            $t->json('meta')->nullable(); // depot, tank, product, plates, etc. snapshot
            $t->timestamps();

            $t->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            $t->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};