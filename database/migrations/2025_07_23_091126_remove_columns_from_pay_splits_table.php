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
        Schema::table('pay_splits', function (Blueprint $table) {
            // drop columns
            // component
            //qte
            //amount
            //total_amount
            $table->dropColumn(['component', 'qte', 'amount', 'total_amount']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pay_splits', function (Blueprint $table) {

        });
    }
};
