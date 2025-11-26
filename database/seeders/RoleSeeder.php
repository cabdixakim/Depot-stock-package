<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run()
    {
        $roles = [
            'admin',
            'operations',
            'accountant',
            'viewer',
            'client'
        ];

        foreach ($roles as $r) {
            DB::table('roles')->updateOrInsert(['name' => $r], ['name' => $r]);
        }
    }
}