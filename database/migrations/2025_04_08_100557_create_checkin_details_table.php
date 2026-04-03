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
        Schema::create('check_in_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('check_in_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('scheduled_hours', 5, 2)->nullable();
            $table->decimal('worked_hours', 5, 2)->nullable();
            $table->decimal('paid_leave', 5, 2)->nullable();
            $table->decimal('sick_leave', 5, 2)->nullable();
            $table->decimal('overtime', 5, 2)->nullable();
            $table->decimal('authorized_absence', 5, 2)->nullable();
            $table->decimal('public_holidays', 5, 2)->nullable();
            $table->decimal('technical_unemployment', 5, 2)->nullable();
            $table->decimal('suspension', 5, 2)->nullable();
            $table->decimal('unpaid_leave', 5, 2)->nullable();
            $table->decimal('unjustified_absence', 5, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checkin_details');
    }
};
