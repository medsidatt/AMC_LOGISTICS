<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fleet_settings')) {
            return;
        }

        Schema::create('fleet_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('monthly_target_tonnage', 12, 2)->default(0);
            $table->decimal('weight_gap_threshold', 6, 2)->default(0.5);
            $table->json('discipline_weights')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_settings');
    }
};
