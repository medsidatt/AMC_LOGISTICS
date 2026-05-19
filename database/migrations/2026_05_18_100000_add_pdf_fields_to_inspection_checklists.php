<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_checklists', function (Blueprint $table) {
            if (!Schema::hasColumn('inspection_checklists', 'driver_id')) {
                $table->foreignId('driver_id')->nullable()->after('truck_id')->constrained()->nullOnDelete();
            }
            if (!Schema::hasColumn('inspection_checklists', 'project_id')) {
                $table->foreignId('project_id')->nullable()->after('driver_id')->constrained()->nullOnDelete();
            }
            if (!Schema::hasColumn('inspection_checklists', 'activity')) {
                $table->string('activity')->nullable()->after('project_id');
            }
            if (!Schema::hasColumn('inspection_checklists', 'client_name')) {
                $table->string('client_name')->nullable()->after('activity');
            }
            if (!Schema::hasColumn('inspection_checklists', 'vehicle_photo_path')) {
                $table->string('vehicle_photo_path')->nullable()->after('attachment_filename');
            }
            if (!Schema::hasColumn('inspection_checklists', 'vehicle_photo_filename')) {
                $table->string('vehicle_photo_filename')->nullable()->after('vehicle_photo_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inspection_checklists', function (Blueprint $table) {
            foreach (['vehicle_photo_filename', 'vehicle_photo_path', 'client_name', 'activity'] as $col) {
                if (Schema::hasColumn('inspection_checklists', $col)) {
                    $table->dropColumn($col);
                }
            }
            if (Schema::hasColumn('inspection_checklists', 'project_id')) {
                $table->dropConstrainedForeignId('project_id');
            }
            if (Schema::hasColumn('inspection_checklists', 'driver_id')) {
                $table->dropConstrainedForeignId('driver_id');
            }
        });
    }
};
