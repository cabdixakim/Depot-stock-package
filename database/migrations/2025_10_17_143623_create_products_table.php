<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('default_density',6,3)->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('products'); }
};
