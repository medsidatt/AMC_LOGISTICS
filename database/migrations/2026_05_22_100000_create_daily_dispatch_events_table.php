<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Materialised timeline of operational events per daily dispatch.
 *
 * Derived from telemetry snapshots, truck stops, fuel events, and place
 * classifications. The same event must be safely re-derivable from the
 * same source rows — dedupe_key ensures idempotency.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_dispatch_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_dispatch_id')->constrained('daily_dispatches')->cascadeOnDelete();
            $table->foreignId('truck_id')->constrained('trucks')->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();

            // queued_at_quarry, loading_started, loaded_and_left,
            // refuel, fuel_loss, long_stop, off_route,
            // border_crossed, arrived_client, unloaded, returning,
            // arrived_base, offline, online, breakdown_suspected
            $table->string('type', 32);

            $table->timestamp('occurred_at')->index();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->foreignId('place_id')->nullable()->constrained('places')->nullOnDelete();

            $table->json('payload')->nullable();

            // gps | ticket | manual | system
            $table->string('source', 16)->default('gps');

            $table->foreignId('snapshot_id')->nullable()
                ->constrained('truck_telemetry_snapshots')->nullOnDelete();

            $table->string('dedupe_key', 64)->unique();

            $table->timestamps();

            $table->index(['daily_dispatch_id', 'occurred_at'], 'dde_dispatch_time_idx');
            $table->index(['truck_id', 'occurred_at'], 'dde_truck_time_idx');
            $table->index(['type', 'occurred_at'], 'dde_type_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_dispatch_events');
    }
};
