<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('trucks', 'fleeti_last_fuel_level')) {
            return;
        }

        Schema::table('trucks', function (Blueprint $table) {
            $table->decimal('fleeti_last_fuel_level', 5, 2)->nullable()->after('fleeti_last_kilometers');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('trucks', 'fleeti_last_fuel_level')) {
            return;
        }

        Schema::table('trucks', function (Blueprint $table) {
            $table->dropColumn('fleeti_last_fuel_level');
        });
    }
};
