<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('check_ins', function (Blueprint $table) {
            // drop foreign key constraint
            $table->dropForeign(['employee_id']);
            $table->dropColumn([
                'employee_id',
                'expected_hours',
                'permission_hours',
                'worked_hours',
                'overtime_hours',
                'leave_hours',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('check_ins', function (Blueprint $table) {
            $table->decimal('worked_hours', 8, 2)->nullable()->after('check_in_to');
            $table->decimal('overtime_hours', 8, 2)->nullable()->after('worked_hours');
            $table->decimal('leave_hours', 8, 2)->nullable()->after('overtime_hours');
            $table->decimal('permission_hours', 8, 2)->nullable()->after('leave_hours');
        });
    }
};
