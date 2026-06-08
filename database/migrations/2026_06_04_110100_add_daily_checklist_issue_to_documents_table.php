<?php

use App\Models\DailyChecklistIssue;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // A document may also be a devis attached to a driver-reported issue.
            $table->foreignIdFor(DailyChecklistIssue::class)
                ->nullable()
                ->after('inspection_checklist_issue_id')
                ->constrained()
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('daily_checklist_issue_id');
        });
    }
};
