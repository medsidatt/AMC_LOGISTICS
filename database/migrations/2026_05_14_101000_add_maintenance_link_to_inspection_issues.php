<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_checklist_issues', function (Blueprint $table) {
            $table->foreignId('maintenance_id')
                ->nullable()
                ->after('resolved_by')
                ->constrained('maintenances')
                ->nullOnDelete();
            $table->index('maintenance_id', 'inspection_issues_maintenance_idx');
        });
    }

    public function down(): void
    {
        Schema::table('inspection_checklist_issues', function (Blueprint $table) {
            $table->dropIndex('inspection_issues_maintenance_idx');
            $table->dropForeign(['maintenance_id']);
            $table->dropColumn('maintenance_id');
        });
    }
};
