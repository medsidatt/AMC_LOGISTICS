<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-truck objective snapshot for a fleet objective period. Targets are frozen
 * at save time (they derive from a rolling window + settings, so recomputing
 * later would drift — an objective must stay a fixed commitment).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_objective_trucks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_objective_id')->constrained()->cascadeOnDelete();
            $table->foreignId('truck_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('target_rotations')->default(0);
            $table->decimal('target_tons', 12, 2)->default(0);
            $table->decimal('capacity_tonnage', 8, 2)->default(0);
            $table->timestamps();

            $table->unique(['fleet_objective_id', 'truck_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_objective_trucks');
    }
};
