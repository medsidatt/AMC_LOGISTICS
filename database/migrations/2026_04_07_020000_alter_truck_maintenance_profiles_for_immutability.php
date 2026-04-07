<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
        Schema::table('truck_maintenance_profiles', function (Blueprint $table) {
            $table->dropIndex('tmp_active_profile_idx');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('deactivated_at');

            $table->unique(['truck_id', 'maintenance_type'], 'truck_maintenance_profile_unique');
        });
    }
};
