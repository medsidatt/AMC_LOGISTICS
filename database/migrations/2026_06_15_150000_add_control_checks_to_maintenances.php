<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Post-work control checklist ("Fiche de contrôle après travaux") — a set of
 * BON / MAUVAIS verifications stored as { key: 'bon'|'mauvais' }.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->json('control_checks')->nullable()->after('battery_status');
        });
    }

    public function down(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->dropColumn('control_checks');
        });
    }
};
