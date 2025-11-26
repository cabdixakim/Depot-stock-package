<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients');
            $table->string('type'); // rent, handling, throughput
            $table->decimal('amount',14,2);
            $table->string('period')->nullable(); // e.g. 2025-10
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('charges'); }
};
