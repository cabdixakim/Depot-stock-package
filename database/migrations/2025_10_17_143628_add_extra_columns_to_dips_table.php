<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('dips', function (Blueprint $table) {
            if (!Schema::hasColumn('dips','temperature')) {
                $table->decimal('temperature', 5, 2)->nullable()->after('observed_volume');
            }
            if (!Schema::hasColumn('dips','density')) {
                $table->decimal('density', 6, 4)->nullable()->after('temperature');
            }
            if (!Schema::hasColumn('dips','book_volume_20')) {
                $table->decimal('book_volume_20', 12, 2)->nullable()->after('volume_20');
            }
            if (!Schema::hasColumn('dips','note')) {
                $table->text('note')->nullable()->after('book_volume_20');
            }
        });
    }
    public function down(): void {
        Schema::table('dips', function (Blueprint $table) {
            $cols = ['temperature','density','book_volume_20','note'];
            foreach ($cols as $c) {
                if (Schema::hasColumn('dips', $c)) $table->dropColumn($c);
            }
        });
    }
};
