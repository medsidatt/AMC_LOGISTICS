<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Operations Calendar (v2 Phase 1) — the pacing foundation. Proration, pacing
 * and projection across the planning engine count OPERATIONAL working days, not
 * calendar days.
 *
 * Design: a calendar carries a default weekly pattern (working_weekdays) so we
 * don't enumerate every working day; calendar_days holds only the exceptions
 * (holidays, shutdowns, or a one-off working day). calendar_id is carried so a
 * future migration can add site/contract calendars without a redesign.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations_calendars', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            // ISO weekday numbers that are working days (1=Mon … 7=Sun). Null ⇒ Mon–Sat.
            $table->json('working_weekdays')->nullable();
            $table->timestamps();
        });

        Schema::create('calendar_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_id')->constrained('operations_calendars')->cascadeOnDelete();
            $table->date('date');
            $table->enum('day_type', ['WORKING_DAY', 'HOLIDAY', 'SHUTDOWN', 'EXCEPTION']);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['calendar_id', 'date']);
        });

        // Seed the single default calendar (Mon–Sat operation).
        DB::table('operations_calendars')->insert([
            'name' => 'Calendrier opérationnel',
            'is_default' => true,
            'working_weekdays' => json_encode([1, 2, 3, 4, 5, 6]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_days');
        Schema::dropIfExists('operations_calendars');
    }
};
