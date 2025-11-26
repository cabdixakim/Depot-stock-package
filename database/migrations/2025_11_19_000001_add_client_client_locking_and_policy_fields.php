<?php
/**
 * FILE NAME:
 * database/migrations/2025_11_18_000000_add_client_locking_and_policy_fields.php
 *
 * PURPOSE:
 *  - Add locking controls to clients (can_load, can_offload, lock_level, lock_reason, lock_override_until)
 *  - Add depot_policies table (for rules like idle stock limits, recommended blocking policy, etc.)
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ================================
        // CLIENT LOCKING FIELDS
        // ================================
        Schema::table('clients', function (Blueprint $table) {

            if (!Schema::hasColumn('clients', 'can_load')) {
                $table->boolean('can_load')
                    ->default(true)
                    ->after('billing_terms'); // keep correct ordering
            }

            if (!Schema::hasColumn('clients', 'can_offload')) {
                $table->boolean('can_offload')
                    ->default(true)
                    ->after('can_load');
            }

            if (!Schema::hasColumn('clients', 'lock_level')) {
                // lock_level = 'none', 'soft', 'hard'
                $table->string('lock_level', 32)
                    ->nullable()
                    ->after('can_offload');
            }

            if (!Schema::hasColumn('clients', 'lock_reason')) {
                $table->text('lock_reason')
                    ->nullable()
                    ->after('lock_level');
            }

            if (!Schema::hasColumn('clients', 'lock_override_until')) {
                $table->timestamp('lock_override_until')
                    ->nullable()
                    ->after('lock_reason');
            }
        });

        // ================================
        // DEPOT POLICIES TABLE
        // ================================
        if (!Schema::hasTable('depot_policies')) {
            Schema::create('depot_policies', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();  // e.g.: idle_limit_days, max_idle_volume
                $table->string('name');            // e.g.: "Idle Stock Cutoff"
                $table->decimal('value_numeric', 20, 4)->nullable(); // numbers like 200000, 10 days, etc.
                $table->string('value_text')->nullable();            // optional string values
                $table->text('notes')->nullable();                   // explanatory notes for admin
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Drop policies table
        if (Schema::hasTable('depot_policies')) {
            Schema::dropIfExists('depot_policies');
        }

        // Drop added columns from clients
        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'can_load')) {
                $table->dropColumn('can_load');
            }
            if (Schema::hasColumn('clients', 'can_offload')) {
                $table->dropColumn('can_offload');
            }
            if (Schema::hasColumn('clients', 'lock_level')) {
                $table->dropColumn('lock_level');
            }
            if (Schema::hasColumn('clients', 'lock_reason')) {
                $table->dropColumn('lock_reason');
            }
            if (Schema::hasColumn('clients', 'lock_override_until')) {
                $table->dropColumn('lock_override_until');
            }
        });
    }
};