<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('places')) {
            return;
        }

        Schema::create('places', function (Blueprint $table) {
            $table->id();

            $table->string('code', 40)->nullable()->unique();
            $table->string('name', 120);
            // 'base' | 'provider_site' | 'client_site' | 'fuel_station' | 'parking' | 'unknown'
            $table->string('type', 20);

            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('radius_m')->default(300);

            $table->foreignId('provider_id')
                ->nullable()
                ->constrained('providers')
                ->nullOnDelete();

            $table->boolean('is_auto_detected')->default(false);
            $table->boolean('is_active')->default(true);

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['type', 'is_active'], 'places_type_active_idx');
            $table->index(['latitude', 'longitude'], 'places_lat_lng_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('places');
    }
};
