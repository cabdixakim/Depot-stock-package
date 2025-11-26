<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tanks', function (Blueprint $table) {
            // put it after product_id for readability
            $table->string('name', 100)->nullable()->after('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('tanks', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};