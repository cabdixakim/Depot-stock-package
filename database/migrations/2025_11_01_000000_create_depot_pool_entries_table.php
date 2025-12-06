<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('depot_pool_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('depot_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->date('date');
            $t->enum('type', ['ALLOWANCE','ADJUSTMENT','TRANSFER_OUT','TRANSFER_IN']);
            $t->decimal('volume_20_l', 15, 3);
            $t->string('ref_type')->nullable();   // OFFLOAD / LOAD / ADJ / TRANSFER
            $t->unsignedBigInteger('ref_id')->nullable();
            $t->text('note')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['depot_id','product_id','date']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('depot_pool_entries');
    }
};
