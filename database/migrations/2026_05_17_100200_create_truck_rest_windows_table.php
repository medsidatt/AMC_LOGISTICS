<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('truck_rest_windows')) {
            return;
        }

        Schema::create('truck_rest_windows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('truck_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('reason', [
                'scheduled_maintenance',
                'oil_change',
                'tire_change',
                'driver_rest',
                'surplus_capacity',
                'anomaly_review',
            ])->default('driver_rest');
            $table->foreignId('maintenance_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['truck_id', 'start_date']);
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('truck_rest_windows');
    }
};
