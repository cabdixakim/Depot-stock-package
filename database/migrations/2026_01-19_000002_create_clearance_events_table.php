<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('clearance_events', function (Blueprint $t) {
            $t->id();

            $t->foreignId('clearance_id')->constrained('clearances')->cascadeOnDelete();

            // created|updated|submitted|tr8_issued|arrived|cancelled|document_uploaded|linked_to_offload
            $t->string('event');

            $t->string('from_status')->nullable();
            $t->string('to_status')->nullable();

            $t->unsignedBigInteger('user_id')->nullable();

            $t->json('meta')->nullable(); // doc_type, old/new values, offload_id, notes, etc.

            $t->timestamps();

            $t->index(['clearance_id', 'event']);
            $t->index(['event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clearance_events');
    }
};