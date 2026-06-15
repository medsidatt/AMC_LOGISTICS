<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nature of the work performed (entretien préventif, réparation, vidange, …) —
 * distinct from maintenance_type which drives the due-tracking logic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->string('work_type', 32)->nullable()->after('maintenance_type');
        });
    }

    public function down(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->dropColumn('work_type');
        });
    }
};
