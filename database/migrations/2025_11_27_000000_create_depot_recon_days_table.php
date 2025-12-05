<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('depot_recon_days', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('tank_id');
            $table->date('date');

            // draft / locked / missing_opening / missing_closing etc
            $table->string('status', 40)->default('draft');

            $table->decimal('opening_l_20', 20, 4)->nullable();
            $table->decimal('closing_expected_l_20', 20, 4)->nullable();
            $table->decimal('closing_actual_l_20', 20, 4)->nullable();
            $table->decimal('variance_l_20', 20, 4)->nullable();
            $table->decimal('variance_pct', 8, 4)->nullable();

            $table->text('note')->nullable();

            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('checked_by_user_id')->nullable();

            $table->timestamps();

            $table->unique(['tank_id', 'date'], 'depot_recon_days_tank_date_unique');
            $table->index('date', 'depot_recon_days_date_idx');
            $table->index('status', 'depot_recon_days_status_idx');

            $table->foreign('tank_id')
                ->references('id')
                ->on('tanks')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depot_recon_days');
    }
};