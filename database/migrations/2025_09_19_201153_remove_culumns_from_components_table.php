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
        Schema::table('components', function (Blueprint $table) {
            // drop columns is_cnss, is_cnam, is_its, nature
            $table->dropColumn(['is_cnss', 'is_cnam', 'is_its', 'nature', 'amount', 'is_summed', 'is_displayed' ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('components', function (Blueprint $table) {
            //
            $table->boolean('is_cnss')->default(false);
            $table->boolean('is_cnam')->default(false);
            $table->boolean('is_its')->default(false);
            $table->enum('nature', ['brut', 'net'])->default('brut');
        });
    }
};
