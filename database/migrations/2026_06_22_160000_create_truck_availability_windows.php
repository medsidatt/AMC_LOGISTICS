<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Availability Windows (v2 Phase 3) — real downtime is the source of truth for
 * truck availability; the per-truck availability/maintenance factors are the
 * fallback used only when no windows exist for the period.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucks', function (Blueprint $table) {
            // Planning fallbacks (used when no real availability windows exist).
            $table->decimal('availability_factor', 4, 3)->default(0.950)->after('capacity_tonnage');
            $table->decimal('maintenance_factor', 4, 3)->default(0.980)->after('availability_factor');
        });

        Schema::create('truck_availability_windows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('truck_id')->constrained()->cascadeOnDelete();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->enum('type', ['REST', 'MAINTENANCE', 'INSPECTION', 'BREAKDOWN', 'SHUTDOWN']);
            $table->string('reason')->nullable();
            $table->enum('source', ['MANUAL', 'SYSTEM'])->default('MANUAL');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['truck_id', 'start_at', 'end_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('truck_availability_windows');

        Schema::table('trucks', function (Blueprint $table) {
            $table->dropColumn(['availability_factor', 'maintenance_factor']);
        });
    }
};
