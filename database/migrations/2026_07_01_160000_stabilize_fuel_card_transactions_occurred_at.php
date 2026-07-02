<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Defect fix (final acceptance audit): `fuel_card_transactions.occurred_at` was a TIMESTAMP carrying
 * MySQL's `ON UPDATE CURRENT_TIMESTAMP` (inherited from the original edk table). Any UPDATE — e.g. a
 * manual review resolving the row — silently rewrote the transaction date to "now", corrupting an
 * immutable financial fact. Convert it to a plain DATETIME so it is never auto-mutated.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE fuel_card_transactions MODIFY occurred_at DATETIME NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE fuel_card_transactions MODIFY occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
    }
};
