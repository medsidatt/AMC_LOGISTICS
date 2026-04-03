<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transport_trackings', function (Blueprint $table) {
            DB::statement("
            ALTER TABLE `transport_trackings`
            MODIFY COLUMN `base` ENUM('mr','sn','none') NULL DEFAULT 'mr'
            ");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transport_trackings', function (Blueprint $table) {
            DB::statement("
                ALTER TABLE transport_trackings
                MODIFY COLUMN base ENUM('mr', 'sn') NOT NULL DEFAULT 'mr'
            ");
        });
    }
};
