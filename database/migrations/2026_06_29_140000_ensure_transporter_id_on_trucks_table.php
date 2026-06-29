<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repair migration: guarantees `trucks.transporter_id` exists.
 *
 * The column is owned by 2025_04_23_170342_create_trucks_table, but some
 * environments (Infomaniak production) ended up without it — causing
 * "Unknown column 'transporter_id'" when creating a new rotation (the form
 * selects trucks.transporter_id and resolveTruck() writes it). This migration
 * is idempotent: a no-op where the column already exists, a fix where it does
 * not. Added nullable (FK, null-on-delete) so it applies cleanly to tables that
 * already hold truck rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('trucks', 'transporter_id')) {
            return; // already present (dev + correctly-migrated environments) — nothing to do
        }

        Schema::table('trucks', function (Blueprint $table) {
            $table->foreignId('transporter_id')
                ->nullable()
                ->after('matricule')
                ->constrained('transporters')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Intentionally a no-op: the column is owned by create_trucks_table.
        // This migration only repairs environments that were missing it, so it
        // must never drop the column on rollback.
    }
};
