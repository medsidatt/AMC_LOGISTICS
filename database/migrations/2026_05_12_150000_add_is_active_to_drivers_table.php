<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ACTIVE_DRIVER_NAMES = [
        'ABDOU AZIZ NDIAYE',
        'EL HADJI DIENG',
        'ALY TOP',
        'TINE CHEIKH',
        'Mor Diaw',
        'BAYE NDIAW SENE',
        'SALIOU NIANG',
        'ABDOU KHADR DIENG',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('drivers')) {
            return;
        }

        Schema::table('drivers', function (Blueprint $table) {
            if (! Schema::hasColumn('drivers', 'is_active')) {
                $table->boolean('is_active')->default(false)->after('user_id');
            }
        });

        DB::table('drivers')
            ->whereIn('name', self::ACTIVE_DRIVER_NAMES)
            ->update(['is_active' => true]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('drivers')) {
            return;
        }

        Schema::table('drivers', function (Blueprint $table) {
            if (Schema::hasColumn('drivers', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};
