<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->enum('type',['IN','OUT','ADJ']);
            $table->date('date');
            $table->foreignId('client_id')->nullable()->constrained('clients');
            $table->foreignId('depot_id')->constrained('depots');
            $table->foreignId('tank_id')->constrained('tanks');
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('truck_id')->nullable()->constrained('trucks');
            $table->decimal('observed_volume',14,3); // litres at observed T
            $table->decimal('temperature',6,2);
            $table->decimal('density',6,3);
            $table->decimal('delivered_20',14,3);
            $table->decimal('allowance_20',14,3)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('transactions'); }
};
