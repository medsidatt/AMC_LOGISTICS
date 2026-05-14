<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_checklists', function (Blueprint $table) {
            $cond = ['ok', 'needs_attention', 'critical', 'na'];
            $table->enum('cabine_fermee', $cond)->nullable()->after('wipers');
            $table->enum('parebrise_vitres', $cond)->nullable()->after('cabine_fermee');
            $table->enum('immatriculation_visible', $cond)->nullable()->after('parebrise_vitres');
        });
    }

    public function down(): void
    {
        Schema::table('inspection_checklists', function (Blueprint $table) {
            $table->dropColumn(['cabine_fermee', 'parebrise_vitres', 'immatriculation_visible']);
        });
    }
};
