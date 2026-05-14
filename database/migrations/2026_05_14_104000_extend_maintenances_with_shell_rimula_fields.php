<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->string('oil_type', 64)->nullable()->after('notes');
            $table->decimal('oil_change_km', 12, 2)->nullable()->after('oil_type');
            $table->decimal('next_oil_change_km', 12, 2)->nullable()->after('oil_change_km');

            $table->string('gearbox_status', 64)->nullable()->after('next_oil_change_km');
            $table->string('differential_status', 64)->nullable()->after('gearbox_status');
            $table->string('hydraulic_status', 64)->nullable()->after('differential_status');
            $table->string('greasing_status', 64)->nullable()->after('hydraulic_status');

            $table->boolean('filter_oil_changed')->default(false)->after('greasing_status');
            $table->boolean('filter_hydraulic_changed')->default(false)->after('filter_oil_changed');
            $table->boolean('filter_air_changed')->default(false)->after('filter_hydraulic_changed');
            $table->boolean('filter_fuel_changed')->default(false)->after('filter_air_changed');

            $table->string('attachment_path')->nullable()->after('filter_fuel_changed');
            $table->text('attachment_url')->nullable()->after('attachment_path');
            $table->string('attachment_filename')->nullable()->after('attachment_url');
        });
    }

    public function down(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->dropColumn([
                'oil_type',
                'oil_change_km',
                'next_oil_change_km',
                'gearbox_status',
                'differential_status',
                'hydraulic_status',
                'greasing_status',
                'filter_oil_changed',
                'filter_hydraulic_changed',
                'filter_air_changed',
                'filter_fuel_changed',
                'attachment_path',
                'attachment_url',
                'attachment_filename',
            ]);
        });
    }
};
