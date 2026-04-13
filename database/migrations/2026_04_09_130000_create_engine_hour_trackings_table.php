<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('engine_hour_trackings')) {
            return;
        }

        Schema::create('engine_hour_trackings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('truck_id')->constrained()->cascadeOnDelete();
            $table->foreignId('telemetry_snapshot_id')
                ->nullable()
                ->constrained('truck_telemetry_snapshots')
                ->nullOnDelete();
            $table->decimal('hours_delta', 8, 2); // increment since last reading
            $table->date('date');
            $table->string('source', 30)->default('fleeti');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['truck_id', 'date'], 'engine_hour_truck_date_idx');
            $table->index('telemetry_snapshot_id', 'engine_hour_snapshot_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engine_hour_trackings');
    }
};
