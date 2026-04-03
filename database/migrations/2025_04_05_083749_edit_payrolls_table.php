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
        Schema::table('payrolls', function (Blueprint $table) {
            // drop foreign key
            $table->dropForeign(['employee_id']);
            $table->dropColumn([
                'employee_id',
                'brut_salary',
                'net_salary',
                'gross_salary',
                'cnss',
                'cnss_p',
                'cnam',
                'cnam_p',
                'its',
                'tax_apprentissage',
                'total_deductions',
                'total_allowances',
                'total_bonuses'
            ]);
            // add columns
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
