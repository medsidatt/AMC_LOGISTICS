<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('maintenances') || Schema::hasColumn('maintenances', 'truck_maintenance_profile_id')) {
            return;
        }

        Schema::table('maintenances', function (Blueprint $table) {
            $table->foreignId('truck_maintenance_profile_id')
                ->nullable()
                ->constrained('truck_maintenance_profiles')
                ->nullOnDelete()
                ->after('truck_id');
            $table->decimal('trigger_km', 15, 2)->nullable()->after('kilometers_at_maintenance');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('maintenances') || !Schema::hasColumn('maintenances', 'truck_maintenance_profile_id')) {
            return;
        }

        Schema::table('maintenances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('truck_maintenance_profile_id');
            $table->dropColumn('trigger_km');
        });
    }
};
