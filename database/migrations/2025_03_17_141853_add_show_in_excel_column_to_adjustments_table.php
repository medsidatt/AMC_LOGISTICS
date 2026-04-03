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
            $table->boolean('show_in_excel')->default(false)->after('amount');
            $table->boolean('has_cnss')->default(true)->after('show_in_excel');
            $table->boolean('has_cnam')->default(true)->after('has_cnss');
            $table->boolean('has_its')->default(true)->after('has_cnam');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('adjustments', function (Blueprint $table) {
            $table->dropColumn('show_in_excel');
            $table->dropColumn('has_cnss');
            $table->dropColumn('has_cnam');
            $table->dropColumn('has_its');
        });
    }
};
