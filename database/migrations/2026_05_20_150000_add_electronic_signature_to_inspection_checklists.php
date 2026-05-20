<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_checklists', function (Blueprint $table) {
            $table->string('electronic_signature_name', 120)->nullable()->after('validation_notes');
        });
    }

    public function down(): void
    {
        Schema::table('inspection_checklists', function (Blueprint $table) {
            $table->dropColumn('electronic_signature_name');
        });
    }
};
