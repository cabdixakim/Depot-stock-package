<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('offloads', function (Blueprint $t) {
            // Additive + nullable = cannot break existing offloads
            $t->foreignId('clearance_id')
                ->nullable()
                ->after('client_id')
                ->constrained('clearances')
                ->nullOnDelete();

            $t->string('compliance_bypass_reason')->nullable()->after('clearance_id');
            $t->string('compliance_bypass_notes')->nullable()->after('compliance_bypass_reason');

            $t->index(['client_id', 'clearance_id']);
        });
    }

    public function down(): void
    {
        Schema::table('offloads', function (Blueprint $t) {
            $t->dropConstrainedForeignId('clearance_id');
            $t->dropColumn(['compliance_bypass_reason', 'compliance_bypass_notes']);
        });
    }
};