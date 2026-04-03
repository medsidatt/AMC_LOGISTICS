<?php

use App\Models\Employee\Employee;
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
        Schema::create('check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Employee::class)->constrained();
            $table->date('check_in_from');
            $table->date('check_in_to');
            $table->decimal('expected_hours', 8, 2);
            $table->decimal('worked_hours', 8, 2)->nullable();
            $table->decimal('overtime_hours', 8, 2)->nullable();
            $table->decimal('leave_hours', 8, 2)->nullable();
            $table->decimal('permission_hours', 8, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('check_ins');
    }
};
