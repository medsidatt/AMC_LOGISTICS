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
        Schema::table('leaves', function (Blueprint $table) {
            $table->dropColumn([
                'last_leave_date',
                'total_leave',
                'leave_taken',
                'leave_balance',
                'cumulative_leave_balance',
            ]);
            $table->date('date')->nullable()->after('employee_id');
            $table->decimal('brut_salary', 10, 2)->nullable()->after('date');
            $table->decimal('net_salary', 10, 2)->nullable()->after('brut_salary');
            $table->integer('acquired_days')->nullable()->after('net_salary');
            $table->decimal('acquired_mru', 10, 2)->nullable()->after('acquired_days');
            $table->integer('used_days')->nullable()->after('acquired_mru');
            $table->decimal('used_mru', 10, 2)->nullable()->after('used_days');
            $table->date('departure_date')->nullable()->after('used_mru');
            $table->date('return_date')->nullable()->after('departure_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            // Revert the changes made in the up method
            $table->dropColumn([
                'date',
                'brut_salary',
                'net_salary',
                'acquired_days',
                'acquired_mru',
                'used_days',
                'used_mru',
                'departure_date',
                'return_date',
            ]);
            $table->date('last_leave_date')->nullable()->after('employee_id');
            $table->integer('total_leave')->nullable()->after('last_leave_date');
            $table->integer('leave_taken')->nullable()->after('total_leave');
            $table->integer('leave_balance')->nullable()->after('leave_taken');
            $table->decimal('cumulative_leave_balance', 10, 2)->nullable()->after('leave_balance');
        });
    }
};
