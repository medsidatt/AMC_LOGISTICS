<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('truck_assignments')) {
            return;
        }

        Schema::create('truck_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('truck_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_demand_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->date('planned_date');
            $table->unsignedSmallInteger('planned_rotations')->default(0);
            $table->decimal('planned_tonnage', 10, 2)->default(0);
            $table->enum('status', ['planned', 'active', 'completed', 'canceled'])->default('planned');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['planned_date', 'status']);
            $table->unique(['truck_id', 'planned_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('truck_assignments');
    }
};
