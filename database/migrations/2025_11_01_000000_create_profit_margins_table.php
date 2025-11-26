<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('profit_margins', function (Blueprint $t) {
            $t->id();
            // NULL = global margin; set client_id to override per-client (future-proof)
            $t->unsignedBigInteger('client_id')->nullable()->index();
            // We store margins at month granularity. Use the 1st of month.
            $t->date('effective_from')->index(); // e.g. 2025-11-01
            $t->decimal('margin_per_litre', 12, 4); // e.g. 10.0000 per L
            $t->timestamps();

            $t->unique(['client_id', 'effective_from']); // one row per client/month (NULL allowed)
        });
    }

    public function down(): void {
        Schema::dropIfExists('profit_margins');
    }
};