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
            // Remove foreign key constraints
            $table->dropForeign(['salary_id']);
            $table->dropForeign(['component_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('salary_component', function (Blueprint $table) {
            // Re-add foreign key constraints
            $table->foreign('salary_id')->references('id')->on('salaries')->onDelete('cascade');
            $table->foreign('component_id')->references('id')->on('components')->onDelete('cascade');
        });
    }
};
