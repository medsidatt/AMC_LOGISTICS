<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fleet planning objective per period — the target (tonnage + rotations) set
 * when a roster is saved. Achievement (effectuée / restante) is computed at
 * read-time from the trips in the period.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_objectives', function (Blueprint $table) {
            $table->id();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('target_tons', 12, 2)->default(0);
            $table->unsignedInteger('target_rotations')->default(0);
            $table->unsignedSmallInteger('working_trucks')->default(0);
            $table->unsignedSmallInteger('rested_trucks')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_objectives');
    }
};
