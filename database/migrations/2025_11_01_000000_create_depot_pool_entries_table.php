<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // If it already exists (local dev, old DB), do nothing.
        if (Schema::hasTable('depot_pool_entries')) {
            return;
        }

        Schema::create('depot_pool_entries', function (Blueprint $t) {
            $t->id();

            $t->foreignId('depot_id')
                ->constrained()
                ->cascadeOnDelete();

            $t->foreignId('product_id')
                ->constrained()
                ->cascadeOnDelete();

            $t->date('date');

            $t->enum('type', [
                'ALLOWANCE',
                'ADJUSTMENT',
                'TRANSFER_OUT',
                'TRANSFER_IN',
            ]);

            $t->decimal('volume_20_l', 15, 3);

            // Include unit_price directly so we don't depend
            // on the later "add_unit_price" migration.
            $t->decimal('unit_price', 15, 4)
              ->nullable();

            $t->string('ref_type')->nullable();   // OFFLOAD / LOAD / ADJ / TRANSFER
            $t->unsignedBigInteger('ref_id')->nullable();

            $t->text('note')->nullable();

            $t->foreignId('created_by')
              ->nullable()
              ->constrained('users')
              ->nullOnDelete();

            $t->timestamps();

            $t->index(['depot_id', 'product_id', 'date']);

            // Include the unique index that the
            // 2025_11_06_*_add_unique_index_to_depot_pool_entries migration adds
            $t->unique(['ref_type', 'ref_id'], 'dpe_ref_type_ref_id_unique');
        });
    }

    public function down(): void
    {
        // Only drop if it exists
        if (Schema::hasTable('depot_pool_entries')) {
            Schema::drop('depot_pool_entries');
        }
    }
};