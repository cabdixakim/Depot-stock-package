<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('trucks', function (Blueprint $table) {
            $table->id();
            $table->string('plate');
            $table->decimal('capacity_l',14,3)->nullable();
            $table->foreignId('client_id')->nullable()->constrained('clients');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('trucks'); }
};
