<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R1.1 — Operational Parameters foundation.
 *
 * Single store for configurable operational VALUES (thresholds, capacities,
 * SLA days, fiscal day, maintenance limits). No business logic lives here.
 * Additive only: nothing consumes this table in R1.1, so behaviour is unchanged.
 * See docs/operational-intelligence-architecture.md (L1) and docs/kpi-catalog.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_parameters', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value');
            $table->string('type')->default('string'); // int|float|bool|string|json
            $table->string('unit')->nullable();
            $table->string('category')->index();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_parameters');
    }
};
