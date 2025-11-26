<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // who did it (nullable for system actions)
            $table->unsignedBigInteger('user_id')->nullable()->index();

            // short action key, e.g. "user.created", "pool.transfer"
            $table->string('action', 100)->index();

            // optional subject (what the action was about)
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable()->index();

            // human readable description
            $table->string('description', 255)->nullable();

            // extra data
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};