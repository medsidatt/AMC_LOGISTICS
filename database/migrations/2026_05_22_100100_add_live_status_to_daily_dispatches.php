<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cache the live operational status of a daily dispatch so the Live Fleet
 * Board UI can read it without recomputing on every render. Written by the
 * fast polling lane (fleeti:sync-live-dispatch) on each tick.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_dispatches', function (Blueprint $table) {
            // French operational labels — see DailyDispatch::STATUS_LIVE_*.
            $table->string('current_status', 32)->nullable()->after('notes');
            $table->timestamp('current_status_at')->nullable()->after('current_status');

            $table->foreignId('current_place_id')->nullable()
                ->after('current_status_at')
                ->constrained('places')
                ->nullOnDelete();

            $table->foreignId('last_event_id')->nullable()
                ->after('current_place_id')
                ->constrained('daily_dispatch_events')
                ->nullOnDelete();

            $table->timestamp('eta_at')->nullable()->after('last_event_id');

            $table->index(['dispatch_date', 'current_status'], 'dd_date_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('daily_dispatches', function (Blueprint $table) {
            $table->dropIndex('dd_date_status_idx');
            $table->dropConstrainedForeignId('last_event_id');
            $table->dropConstrainedForeignId('current_place_id');
            $table->dropColumn(['current_status', 'current_status_at', 'eta_at']);
        });
    }
};
