<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('truck_maintenance_profiles')) {
            return; // Table doesn't exist yet, create migration will handle it
        }

        // Only alter if the columns don't already exist (i.e. table was created before this column was added)
        if (Schema::hasColumn('truck_maintenance_profiles', 'created_by')) {
            return; // Already has the new columns from the updated create migration
        }

        Schema::table('truck_maintenance_profiles', function (Blueprint $table) {
            // Drop unique constraint to allow multiple profiles per truck+type (active + inactive)
            $table->dropUnique('truck_maintenance_profile_unique');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->after('is_active');
            $table->timestamp('deactivated_at')->nullable()->after('created_by');

            $table->index(['truck_id', 'maintenance_type', 'is_active'], 'tmp_active_profile_idx');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('truck_maintenance_profiles') || !Schema::hasColumn('truck_maintenance_profiles', 'created_by')) {
            return;
        }


        Schema::table('truck_maintenance_profiles', function (Blueprint $table) {
            $table->dropIndex('tmp_active_profile_idx');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('deactivated_at');
        });
    }
};
