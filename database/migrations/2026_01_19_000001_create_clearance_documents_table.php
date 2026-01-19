<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('clearance_documents', function (Blueprint $t) {
            $t->id();

            $t->foreignId('clearance_id')->constrained('clearances')->cascadeOnDelete();

            // invoice|delivery_note|tr8|other
            $t->string('type')->default('other');

            $t->string('file_path');
            $t->string('original_name')->nullable();

            $t->unsignedBigInteger('uploaded_by')->nullable();

            $t->timestamps();

            $t->index(['clearance_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clearance_documents');
    }
};