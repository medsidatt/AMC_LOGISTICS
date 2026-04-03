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
        Schema::table('transport_trackings', function (Blueprint $table) {
            $table->enum('base', ['mr', 'sn'])->default('mr')->after('product');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transport_trackings', function (Blueprint $table) {
            $table->dropColumn('base');
        });
    }
};
