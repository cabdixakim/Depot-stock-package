<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('offloads', 'link_clearance')) {
            Schema::table('offloads', function (Blueprint $table) {
                $table
                    ->boolean('link_clearance')
                    ->default(false)
                    ->after('note')
                    ->comment('Whether this offload is linked to a compliance clearance');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('offloads', 'link_clearance')) {
            Schema::table('offloads', function (Blueprint $table) {
                $table->dropColumn('link_clearance');
            });
        }
    }
};