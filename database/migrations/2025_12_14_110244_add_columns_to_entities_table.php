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
        Schema::table('entities', function (Blueprint $table) {
            $table->string('ilot')->nullable()->after('address');
            $table->string('lot')->nullable()->after('ilot');
            $table->string('city')->nullable()->after('lot');
            $table->string('activity_principle')->nullable()->after('city');
            $table->string('bp')->nullable()->after('activity_principle');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->dropColumn(['ilot', 'lot', 'city', 'activity_principle', 'bp']);
        });
    }
};
