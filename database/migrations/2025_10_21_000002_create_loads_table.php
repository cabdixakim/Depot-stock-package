<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('loads', function (Blueprint $t) {
            $t->id();
            $t->date('date');
            $t->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('depot_id')->constrained()->cascadeOnDelete();
            $t->foreignId('tank_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();

            $t->decimal('loaded_observed_l', 12, 3)->nullable();
            $t->decimal('loaded_20_l', 12, 3)->default(0);

            $t->decimal('temperature_c', 5, 2)->nullable();
            $t->decimal('density_kg_l', 6, 4)->nullable();
            $t->string('reference')->nullable();
            $t->string('note')->nullable();

            $t->timestamps();

            $t->index(['date']);
            $t->index(['client_id', 'date']);
            $t->index(['tank_id', 'date']);
        });
    }
    public function down(): void { Schema::dropIfExists('loads'); }
};
