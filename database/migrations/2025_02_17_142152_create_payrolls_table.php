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
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('brut_salary', 10, 2);
            $table->decimal('net_salary', 10, 2);
            $table->decimal('gross_salary', 10, 2);
            $table->decimal('cnss', 10, 2);
            $table->decimal('cnss_p', 10, 2);
            $table->decimal('cnam', 10, 2);
            $table->decimal('cnam_p', 10, 2);
            $table->decimal('its', 10, 2);
            $table->decimal('tax_apprentissage', 10, 2);
            $table->decimal('total_deductions', 10, 2);
            $table->decimal('total_allowances', 10, 2);
            $table->decimal('total_bonuses', 10, 2);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
