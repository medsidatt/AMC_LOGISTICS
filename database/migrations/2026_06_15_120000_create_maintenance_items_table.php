<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Custom line items (facture) attached to a maintenance record — mirrors the
 * "BON AMC TRAVAUX" work order: Désignation | Référence | Quantité | Prix U | Prix Total.
 * Lets the operator add arbitrary parts / oil / labour lines during a maintenance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_id')->constrained()->cascadeOnDelete();
            $table->string('designation');
            $table->string('reference')->nullable();
            $table->string('category', 16)->default('piece'); // piece | huile | autre
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_items');
    }
};
