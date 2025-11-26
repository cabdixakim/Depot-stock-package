<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // 1) roles table
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();   // admin, operations, accountant, viewer, client
            $table->timestamps();
        });

        // 2) pivot table: users ↔ roles
        Schema::create('role_user', function (Blueprint $table) {
            $table->id();

            // assuming your main app already has users table
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->foreignId('role_id')
                ->constrained('roles')
                ->onDelete('cascade');

            $table->timestamps();

            $table->unique(['user_id', 'role_id']); // prevent duplicates
        });

        // 3) Seed default roles directly in the migration (so no seeder needed)
        $now = now();

        $roles = [
            ['name' => 'admin',       'created_at' => $now, 'updated_at' => $now],
            ['name' => 'operations',  'created_at' => $now, 'updated_at' => $now],
            ['name' => 'accountant',  'created_at' => $now, 'updated_at' => $now],
            ['name' => 'viewer',      'created_at' => $now, 'updated_at' => $now],
            ['name' => 'client',      'created_at' => $now, 'updated_at' => $now],
        ];

        // Insert only if table is empty (so we don’t duplicate if migration re-runs in some weird env)
        if (DB::table('roles')->count() === 0) {
            DB::table('roles')->insert($roles);
        }
    }

    public function down()
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
    }
};