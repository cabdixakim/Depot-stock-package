<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices');
            $table->foreignId('client_id')->constrained('clients');
            $table->date('date');
            $table->decimal('amount',14,2);
            $table->string('mode'); // cash, bank, etc.
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('payments'); }
};
