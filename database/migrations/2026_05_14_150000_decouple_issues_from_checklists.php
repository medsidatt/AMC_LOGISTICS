<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_checklist_issues', function (Blueprint $table) {
            $table->foreignId('truck_id')->nullable()->after('daily_checklist_id')->constrained()->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->after('truck_id')->constrained()->nullOnDelete();
            $table->string('severity', 16)->nullable()->after('flagged');
            $table->timestamp('reported_at')->nullable()->after('severity');
            $table->index(['truck_id', 'reported_at'], 'daily_checklist_issues_truck_reported_idx');
        });

        // daily_checklist_id was previously required because the column was created without nullable().
        // Make it nullable so issues can be reported independently.
        Schema::table('daily_checklist_issues', function (Blueprint $table) {
            $table->foreignId('daily_checklist_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('daily_checklist_issues', function (Blueprint $table) {
            $table->dropIndex('daily_checklist_issues_truck_reported_idx');
            $table->dropConstrainedForeignId('truck_id');
            $table->dropConstrainedForeignId('driver_id');
            $table->dropColumn(['severity', 'reported_at']);
        });
    }
};
