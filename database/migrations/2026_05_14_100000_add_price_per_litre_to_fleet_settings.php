<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fleet_settings')) {
            return;
        }

        Schema::table('fleet_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('fleet_settings', 'price_per_litre')) {
                $table->decimal('price_per_litre', 8, 2)->default(730);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fleet_settings')) {
            return;
        }

        Schema::table('fleet_settings', function (Blueprint $table) {
            if (Schema::hasColumn('fleet_settings', 'price_per_litre')) {
                $table->dropColumn('price_per_litre');
            }
        });
    }
};
