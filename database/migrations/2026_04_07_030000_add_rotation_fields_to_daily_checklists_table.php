<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('daily_checklists') || Schema::hasColumn('daily_checklists', 'start_km')) {
            return;
        }

        Schema::table('daily_checklists', function (Blueprint $table) {
            $table->decimal('start_km', 15, 2)->nullable()->after('checklist_date');
            $table->decimal('end_km', 15, 2)->nullable()->after('start_km');
            $table->decimal('fuel_filled', 8, 2)->nullable()->after('fuel_refill');
            $table->foreignId('transport_tracking_id')->nullable()->constrained('transport_trackings')->nullOnDelete()->after('driver_id');
        });
    }

    public function down(): void
    {
        Schema::table('daily_checklists', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transport_tracking_id');
            $table->dropColumn(['start_km', 'end_km', 'fuel_filled']);
        });
    }
};
