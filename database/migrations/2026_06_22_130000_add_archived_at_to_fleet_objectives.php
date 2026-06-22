<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft-archive for objectives. Archived objectives are kept for history but
 * excluded from hierarchical target resolution so they no longer drive the
 * scoreboard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fleet_objectives', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('fleet_objectives', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
    }
};
