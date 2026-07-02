<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F1.2/F1.3 — Re-scope the EDK table to its true meaning.
 *
 * The `edk_fuel_transactions` table never held fuel *purchases*: the EDK exports are card/account
 * *recharges* (money top-ups), and its `litres` were only ever `amount ÷ price` estimates. This
 * migration renames the table to `edk_fuel_recharges` and the misleading `litres` column to
 * `estimated_litres`, and adds the UNIQUE (transaction_id, truck_id) constraint the racy
 * app-level dedupe never guaranteed (financial-data safety).
 *
 * See docs/fuel-edk-reclassification.md (approved). No FuelPurchase/FuelStation is introduced.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('edk_fuel_transactions') && ! Schema::hasTable('edk_fuel_recharges')) {
            Schema::rename('edk_fuel_transactions', 'edk_fuel_recharges');
        }

        if (Schema::hasTable('edk_fuel_recharges') && Schema::hasColumn('edk_fuel_recharges', 'litres')) {
            Schema::table('edk_fuel_recharges', function (Blueprint $table) {
                $table->renameColumn('litres', 'estimated_litres');
            });
        }

        // Collapse any pre-existing duplicates before enforcing uniqueness (the old dedupe was racy).
        // NULL transaction_id rows are left untouched (SQL treats NULLs as distinct anyway).
        DB::statement(<<<'SQL'
            DELETE t1 FROM edk_fuel_recharges t1
            INNER JOIN edk_fuel_recharges t2
            WHERE t1.id > t2.id
              AND t1.truck_id = t2.truck_id
              AND t1.transaction_id = t2.transaction_id
              AND t1.transaction_id IS NOT NULL
        SQL);

        Schema::table('edk_fuel_recharges', function (Blueprint $table) {
            $table->unique(['transaction_id', 'truck_id'], 'edk_recharges_txn_truck_unique');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('edk_fuel_recharges')) {
            Schema::table('edk_fuel_recharges', function (Blueprint $table) {
                $table->dropUnique('edk_recharges_txn_truck_unique');
            });

            if (Schema::hasColumn('edk_fuel_recharges', 'estimated_litres')) {
                Schema::table('edk_fuel_recharges', function (Blueprint $table) {
                    $table->renameColumn('estimated_litres', 'litres');
                });
            }

            Schema::rename('edk_fuel_recharges', 'edk_fuel_transactions');
        }
    }
};
