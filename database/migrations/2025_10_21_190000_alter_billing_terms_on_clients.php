<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $t) {
            // change to VARCHAR(255) NULL
            $t->string('billing_terms', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $t) {
            // if you really had DECIMAL before, revert carefully; otherwise leave as string
            // $t->decimal('billing_terms', 10, 2)->nullable()->change();
        });
    }
};
