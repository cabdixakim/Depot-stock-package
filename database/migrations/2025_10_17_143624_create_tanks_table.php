<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tanks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('depot_id')->constrained('depots');
            $table->foreignId('product_id')->constrained('products');
            $table->decimal('capacity_l',14,3)->nullable();
            $table->string('strapping_chart_path')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('tanks'); }
};
