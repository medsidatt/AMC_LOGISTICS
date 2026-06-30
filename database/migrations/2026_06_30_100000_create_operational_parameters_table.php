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
            // `value` stays text + a `type` discriminator: this stores scalars AND
            // json-encoded structures without a future migration per new type (ADR-008).
            $table->text('value');
            $table->string('type')->default('string'); // int|float|bool|string|json
            $table->string('unit')->nullable();
            $table->string('category')->index();
            $table->string('owner')->index();
            $table->text('description')->nullable();
            // Governance metadata (ADR-008)
            $table->boolean('is_active')->default(true);
            $table->boolean('editable')->default(true);
            $table->boolean('deprecated')->default(false);
            $table->string('introduced_by_adr')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_parameters');
    }
};
