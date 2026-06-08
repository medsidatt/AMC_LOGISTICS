<?php

use App\Models\InspectionChecklistIssue;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // A document now belongs to either a transport tracking OR an
            // inspection issue (e.g. a devis attached to a flagged finding).
            $table->unsignedBigInteger('transport_tracking_id')->nullable()->change();

            $table->foreignIdFor(InspectionChecklistIssue::class)
                ->nullable()
                ->after('transport_tracking_id')
                ->constrained()
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inspection_checklist_issue_id');
            $table->unsignedBigInteger('transport_tracking_id')->nullable(false)->change();
        });
    }
};
