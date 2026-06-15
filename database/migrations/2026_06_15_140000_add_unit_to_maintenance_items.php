<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quantity unit per facture line (piece, litre, kg, …) — oil is billed in
 * litres, parts in pieces, etc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_items', function (Blueprint $table) {
            $table->string('unit', 16)->default('piece')->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_items', function (Blueprint $table) {
            $table->dropColumn('unit');
        });
    }
};
