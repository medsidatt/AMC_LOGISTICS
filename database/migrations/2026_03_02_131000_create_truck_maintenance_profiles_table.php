<?php

use App\Models\Truck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('truck_maintenance_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Truck::class)->constrained()->cascadeOnDelete();
            $table->string('maintenance_type', 50);
            $table->decimal('interval_km', 10, 2)->default(10000);
            $table->decimal('warning_threshold_km', 10, 2)->nullable();
            $table->decimal('last_maintenance_km', 15, 2)->default(0);
            $table->decimal('next_maintenance_km', 15, 2)->default(10000);
            $table->string('status', 20)->default('green');
            $table->timestamp('last_calculated_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['truck_id', 'maintenance_type'], 'truck_maintenance_profile_unique');
            $table->index(['truck_id', 'status', 'next_maintenance_km'], 'truck_maintenance_profile_lookup_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('truck_maintenance_profiles');
    }
};
