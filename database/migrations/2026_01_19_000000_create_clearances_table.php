<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('clearances', function (Blueprint $t) {
            $t->id();

            // Scope / ownership
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->foreignId('depot_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            // Workflow
            $t->string('status')->default('draft'); // draft|submitted|tr8_issued|arrived|offloaded|cancelled
            $t->boolean('is_bonded')->default(false);

            // Truck movement identity
            $t->string('truck_number')->nullable();
            $t->string('trailer_number')->nullable();

            // Declared document values (from invoice/delivery note)
            $t->decimal('loaded_20_l', 12, 3)->nullable(); // Loaded quantity @20°C (declared)
            $t->string('invoice_number')->nullable();
            $t->string('delivery_note_number')->nullable();

            // Border / clearance
            $t->string('border_point')->nullable();
            $t->timestamp('submitted_at')->nullable();
            $t->unsignedBigInteger('submitted_by')->nullable();

            // TR8
            $t->string('tr8_number')->nullable();
            $t->timestamp('tr8_issued_at')->nullable();

            // Arrival (optional checkpoint)
            $t->timestamp('arrived_at')->nullable();

            // Notes + who opened the case
            $t->text('notes')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();

            $t->timestamps();

            // Indexes
            $t->index(['client_id', 'status']);
            $t->index(['status']);
            $t->index(['truck_number']);
            $t->index(['trailer_number']);
            $t->index(['tr8_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clearances');
    }
};