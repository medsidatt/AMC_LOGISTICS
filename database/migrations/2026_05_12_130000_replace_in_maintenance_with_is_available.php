<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trucks')) {
            return;
        }

        Schema::table('trucks', function (Blueprint $table) {
            if (! Schema::hasColumn('trucks', 'is_available')) {
                $table->boolean('is_available')->default(true)->after('is_active');
            }
        });

        if (Schema::hasColumn('trucks', 'in_maintenance')) {
            DB::table('trucks')->update([
                'is_available' => DB::raw('NOT in_maintenance'),
            ]);

            Schema::table('trucks', function (Blueprint $table) {
                $table->dropColumn('in_maintenance');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('trucks')) {
            return;
        }

        Schema::table('trucks', function (Blueprint $table) {
            if (! Schema::hasColumn('trucks', 'in_maintenance')) {
                $table->boolean('in_maintenance')->default(false)->after('is_active');
            }
        });

        if (Schema::hasColumn('trucks', 'is_available')) {
            DB::table('trucks')->update([
                'in_maintenance' => DB::raw('NOT is_available'),
            ]);

            Schema::table('trucks', function (Blueprint $table) {
                $table->dropColumn('is_available');
            });
        }
    }
};
