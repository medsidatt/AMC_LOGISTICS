<?php

use App\Models\Truck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_checklists', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(Truck::class)->constrained()->cascadeOnDelete();
            $table->foreignId('inspector_id')->constrained('users')->cascadeOnDelete();

            $table->date('inspection_date');
            $table->enum('category', ['safety', 'compliance', 'mechanical', 'comprehensive'])->default('comprehensive');

            $cond = ['ok', 'needs_attention', 'critical', 'na'];
            $table->enum('seatbelts', $cond)->nullable();
            $table->enum('extinguisher_status', $cond)->nullable();
            $table->enum('first_aid_kit', $cond)->nullable();
            $table->enum('reflective_triangles', $cond)->nullable();
            $table->enum('tire_tread_depth', $cond)->nullable();
            $table->enum('brake_test_result', $cond)->nullable();
            $table->enum('lights_full_check', $cond)->nullable();
            $table->enum('mirrors', $cond)->nullable();
            $table->enum('horn', $cond)->nullable();
            $table->enum('steering_play', $cond)->nullable();
            $table->enum('suspension', $cond)->nullable();
            $table->enum('exhaust_emissions', $cond)->nullable();
            $table->enum('chassis_condition', $cond)->nullable();
            $table->enum('cargo_securing_equipment', $cond)->nullable();

            $table->text('findings_summary')->nullable();
            $table->text('recommendations')->nullable();

            $table->enum('status', ['draft', 'submitted', 'validated', 'rejected'])->default('draft');
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->text('validation_notes')->nullable();

            $table->string('sharepoint_item_id')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['truck_id', 'inspection_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_checklists');
    }
};
