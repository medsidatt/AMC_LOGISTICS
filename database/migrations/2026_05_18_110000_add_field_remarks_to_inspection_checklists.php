<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_checklists', function (Blueprint $table) {
            if (!Schema::hasColumn('inspection_checklists', 'field_remarks')) {
                $table->json('field_remarks')->nullable()->after('recommendations');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inspection_checklists', function (Blueprint $table) {
            if (Schema::hasColumn('inspection_checklists', 'field_remarks')) {
                $table->dropColumn('field_remarks');
            }
        });
    }
};
