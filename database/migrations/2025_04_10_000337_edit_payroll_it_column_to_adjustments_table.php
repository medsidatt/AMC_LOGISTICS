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
        Schema::table('adjustments', function (Blueprint $table) {
            $table->dropForeign(['payroll_id']);
            $table->dropColumn('payroll_id');
            $table->foreignId('payroll_detail_id')->after('id')->constrained()->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('adjustments', function (Blueprint $table) {
            $table->dropForeign(['payroll_detail_id']);
            $table->dropColumn('payroll_detail_id');
            $table->foreignId('payroll_id')->after('id')->constrained()->onDelete('cascade');
        });
    }
};
