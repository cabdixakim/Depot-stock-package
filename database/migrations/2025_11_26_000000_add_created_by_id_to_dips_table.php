<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('dips', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by_id')
                  ->nullable()
                  ->after('id');
            // If you REALLY want an FK and your users table is `users`, you can uncomment:
            // $table->foreign('created_by_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dips', function (Blueprint $table) {
            // If you added FK above, drop it first:
            // $table->dropForeign(['created_by_id']);
            $table->dropColumn('created_by_id');
        });
    }
};