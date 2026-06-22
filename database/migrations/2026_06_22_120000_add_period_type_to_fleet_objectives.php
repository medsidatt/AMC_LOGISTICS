<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-level planning objectives: an objective is no longer implicitly weekly.
 * period_type distinguishes WEEK / MONTH / YEAR / CUSTOM so a week and the month
 * containing it can coexist, enabling hierarchical target resolution.
 *
 * Existing rows are all weekly snapshots → backfilled to 'WEEK' by the default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fleet_objectives', function (Blueprint $table) {
            $table->enum('period_type', ['WEEK', 'MONTH', 'YEAR', 'CUSTOM'])
                ->default('WEEK')
                ->after('id');
        });

        // A week and the month that contains it share no (period_type,start,end),
        // so widen the uniqueness to include period_type.
        Schema::table('fleet_objectives', function (Blueprint $table) {
            $table->dropUnique(['start_date', 'end_date']);
            $table->unique(['period_type', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::table('fleet_objectives', function (Blueprint $table) {
            $table->dropUnique(['period_type', 'start_date', 'end_date']);
            $table->unique(['start_date', 'end_date']);
        });

        Schema::table('fleet_objectives', function (Blueprint $table) {
            $table->dropColumn('period_type');
        });
    }
};
