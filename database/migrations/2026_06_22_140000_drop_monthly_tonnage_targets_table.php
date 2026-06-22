<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the duplicate planning-objective store. Objectives are owned solely by
 * fleet_objectives; the KPI services now read planned tonnage from there via
 * ObjectiveTargetResolver. (Single source of truth.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('monthly_tonnage_targets');
    }

    public function down(): void
    {
        Schema::create('monthly_tonnage_targets', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('year');
            $table->tinyInteger('month');
            $table->decimal('target_tonnage', 12, 2)->default(0);
            $table->timestamps();
            $table->unique(['year', 'month']);
        });
    }
};
