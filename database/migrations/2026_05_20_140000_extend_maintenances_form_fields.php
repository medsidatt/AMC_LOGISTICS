<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->decimal('oil_quantity_liters', 6, 2)->nullable()->after('next_oil_change_km');
            $table->string('brake_status', 32)->nullable()->after('greasing_status');
            $table->string('coolant_status', 32)->nullable()->after('brake_status');
            $table->string('battery_status', 32)->nullable()->after('coolant_status');
            $table->string('dashboard_photo_path')->nullable()->after('attachment_filename');
            $table->string('dashboard_photo_filename')->nullable()->after('dashboard_photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->dropColumn([
                'oil_quantity_liters',
                'brake_status',
                'coolant_status',
                'battery_status',
                'dashboard_photo_path',
                'dashboard_photo_filename',
            ]);
        });
    }
};
