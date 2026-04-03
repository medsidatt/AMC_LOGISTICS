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
        Schema::create('salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->decimal('total_salary', 10, 0)->default(0);
            $table->decimal('its', 10, 0)->default(0);
            $table->decimal('cnss', 10, 0)->default(0);
            $table->decimal('cnam', 10, 0)->default(0);
            $table->decimal('cnss_p', 10, 0)->default(0);
            $table->decimal('cnam_p', 10, 0)->default(0);
            $table->decimal('brut_salary', 10, 0)->default(0);
            $table->decimal('salary_mass', 10, 0)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salaries');
    }
};
