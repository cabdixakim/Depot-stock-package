<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // roles table exists already in your package
        if (!DB::table('roles')->where('name', 'compliance')->exists()) {
            DB::table('roles')->insert([
                'name' => 'compliance',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'compliance')->delete();
    }
};