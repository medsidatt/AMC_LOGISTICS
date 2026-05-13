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
            if (! Schema::hasColumn('trucks', 'in_maintenance')) {
                $table->boolean('in_maintenance')->default(false)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('trucks')) {
            return;
        }

        Schema::table('trucks', function (Blueprint $table) {
            if (Schema::hasColumn('trucks', 'in_maintenance')) {
                $table->dropColumn('in_maintenance');
            }
        });
    }
};
