<?php

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
        Schema::create('payroll_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_id')->constrained()->cascadeOnDelete();
            $table->decimal('brut_salary', 10, 2)->nullable();
            $table->decimal('net_salary', 10, 2)->nullable();
            $table->decimal('gross_salary', 10, 2)->nullable();
            $table->decimal('cnss', 10, 2)->nullable();
            $table->decimal('cnss_p', 10, 2)->nullable();
            $table->decimal('cnam', 10, 2)->nullable();
            $table->decimal('cnam_p', 10, 2)->nullable();
            $table->decimal('its', 10, 2)->nullable();
            $table->decimal('apprenticeship_tax', 10, 2)->nullable();
            $table->decimal('total_deductions', 10, 2)->nullable();
            $table->decimal('total_allowances', 10, 2)->nullable();
            $table->decimal('total_bonuses', 10, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_details');
    }
};
