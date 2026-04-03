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
            // Adding 'code' column to the adjustments table
            $table->string('code')->nullable()->after('type');
            // note
            $table->string('note')->nullable()->after('code');
            // nature
            $table->enum('nature', ['brut', 'net'])->default('brut')->after('note');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('adjustments', function (Blueprint $table) {
            // Dropping 'code', 'note', and 'nature' columns from the adjustments table
            $table->dropColumn(['code', 'note', 'nature']);
        });
    }
};
