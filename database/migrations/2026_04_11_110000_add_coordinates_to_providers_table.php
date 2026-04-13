<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('providers')) {
            return;
        }

        Schema::table('providers', function (Blueprint $table) {
            if (! Schema::hasColumn('providers', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('email');
            }
            if (! Schema::hasColumn('providers', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('providers')) {
            return;
        }

        Schema::table('providers', function (Blueprint $table) {
            if (Schema::hasColumn('providers', 'longitude')) {
                $table->dropColumn('longitude');
            }
            if (Schema::hasColumn('providers', 'latitude')) {
                $table->dropColumn('latitude');
            }
        });
    }
};
