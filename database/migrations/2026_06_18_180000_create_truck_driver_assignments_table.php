<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Driver↔truck assignments with role (titulaire/assistant) and history.
 * Active assignment = ended_at NULL. Invariants (enforced in the service):
 * one active assignment per driver; one active titulaire + one active
 * assistant per truck.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('truck_driver_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('truck_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16)->default('titulaire'); // titulaire | assistant
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['truck_id', 'ended_at']);
            $table->index(['driver_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('truck_driver_assignments');
    }
};
