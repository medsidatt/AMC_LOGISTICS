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
        Schema::table('pay_splits', function (Blueprint $table) {
            // employee_id foreignId
            $table->foreignIdFor(Employee::class)->after('id')->nullable()->constrained()->nullOnDelete()->comment('Foreign key to the employees table');
            // type enum
            $table->enum('type', ['default', 'global', 'net_only'])->after('payroll_detail_id')->default('default')->comment('default: default, global: global, net_only: net only');
            // start_date date
            $table->date('start_date')->after('type')->nullable()->comment('Start date of the pay split');
            // end_date date
            $table->date('end_date')->after('start_date')->nullable()->comment('End date of the pay split');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pay_splits', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropColumn('employee_id');
            $table->dropColumn('type');
            $table->dropColumn('start_date');
            $table->dropColumn('end_date');
        });
    }
};
