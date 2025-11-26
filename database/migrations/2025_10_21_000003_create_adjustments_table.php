<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('adjustments', function (Blueprint $t) {
            $t->id();
            $t->date('date');
            $t->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('depot_id')->constrained()->cascadeOnDelete();
            $t->foreignId('tank_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();

            $t->decimal('amount_20_l', 12, 3); // +/- liters at 20°C
            $t->string('reason')->nullable();
            $t->string('reference')->nullable();
            $t->string('note')->nullable();

            $t->timestamps();

            $t->index(['date']);
            $t->index(['client_id', 'date']);
            $t->index(['tank_id', 'date']);
        });
    }
    public function down(): void { Schema::dropIfExists('adjustments'); }
};
