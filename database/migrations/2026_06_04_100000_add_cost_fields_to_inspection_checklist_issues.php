<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_checklist_issues', function (Blueprint $table) {
            // Repair cost for this finding, in FCFA. parts + labor = total.
            $table->decimal('parts_cost', 14, 2)->nullable()->after('resolution_notes');
            $table->decimal('labor_cost', 14, 2)->nullable()->after('parts_cost');
            $table->decimal('total_cost', 14, 2)->nullable()->after('labor_cost');
            $table->foreignId('cost_recorded_by')->nullable()->after('total_cost')->constrained('users')->nullOnDelete();
            $table->timestamp('cost_recorded_at')->nullable()->after('cost_recorded_by');
        });
    }

    public function down(): void
    {
        Schema::table('inspection_checklist_issues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cost_recorded_by');
            $table->dropColumn(['parts_cost', 'labor_cost', 'total_cost', 'cost_recorded_at']);
        });
    }
};
