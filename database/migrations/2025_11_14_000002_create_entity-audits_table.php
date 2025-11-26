<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('entity_audits')) {
            Schema::create('entity_audits', function (Blueprint $table) {
                $table->id();
                $table->string('entity_type', 100);
                $table->unsignedBigInteger('entity_id');
                $table->string('action', 100);
                $table->json('before')->nullable();
                $table->json('after')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_audits');
    }
};