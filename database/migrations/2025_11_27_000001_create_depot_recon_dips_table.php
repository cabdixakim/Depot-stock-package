<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('depot_recon_dips', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('recon_day_id');
            // opening / closing
            $table->string('type', 20);

            $table->decimal('dip_height_cm', 10, 3)->nullable();
            $table->decimal('temperature_c', 6, 2)->nullable();
            $table->decimal('density_kg_l', 8, 4)->nullable();
            $table->decimal('volume_20_l', 20, 4)->nullable();

            $table->timestamp('captured_at')->nullable();
            $table->text('note')->nullable();

            $table->unsignedBigInteger('created_by_user_id')->nullable();

            $table->timestamps();

            $table->index('recon_day_id', 'depot_recon_dips_recon_day_idx');
            $table->index('type', 'depot_recon_dips_type_idx');

            $table->foreign('recon_day_id')
                ->references('id')
                ->on('depot_recon_days')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depot_recon_dips');
    }
};