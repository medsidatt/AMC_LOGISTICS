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
        Schema::table('salary_component', function (Blueprint $table) {
            $table->enum('nature', ['brut', 'net'])->default('net')->after('amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('salary_component', function (Blueprint $table) {
            $table->dropColumn('nature');
        });
    }
};
