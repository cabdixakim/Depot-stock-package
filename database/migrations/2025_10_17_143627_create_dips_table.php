<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('dips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tank_id')->constrained('tanks');
            $table->date('date');
            $table->decimal('dip_height',10,3);
            $table->decimal('observed_volume',14,3)->nullable();
            $table->decimal('volume_20',14,3)->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('dips'); }
};
