<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trucks')) {
            return;
        }

        Schema::table('trucks', function (Blueprint $table) {
            if (! Schema::hasColumn('trucks', 'capacity_tonnage')) {
                $table->decimal('capacity_tonnage', 8, 2)->default(25)->after('matricule');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('trucks')) {
            return;
        }

        Schema::table('trucks', function (Blueprint $table) {
            if (Schema::hasColumn('trucks', 'capacity_tonnage')) {
                $table->dropColumn('capacity_tonnage');
            }
        });
    }
};
