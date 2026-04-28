<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_checklists', function (Blueprint $table) {
            $table->date('week_start_date')->nullable()->after('checklist_date');
            $table->enum('status', ['pending', 'validated', 'rejected'])->default('pending')->after('sharepoint_item_id');
            $table->foreignId('validated_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable()->after('validated_by');
            $table->text('validation_notes')->nullable()->after('validated_at');
        });

        DB::statement("UPDATE daily_checklists SET week_start_date = DATE_SUB(checklist_date, INTERVAL WEEKDAY(checklist_date) DAY) WHERE week_start_date IS NULL");

        DB::statement("UPDATE daily_checklists SET status = 'validated', validated_at = updated_at WHERE status = 'pending' AND created_at < NOW()");

        $duplicates = DB::table('daily_checklists')
            ->select('truck_id', 'week_start_date', DB::raw('COUNT(*) as cnt'), DB::raw('MAX(id) as keep_id'))
            ->whereNull('deleted_at')
            ->groupBy('truck_id', 'week_start_date')
            ->having('cnt', '>', 1)
            ->get();

        foreach ($duplicates as $dup) {
            DB::table('daily_checklists')
                ->where('truck_id', $dup->truck_id)
                ->where('week_start_date', $dup->week_start_date)
                ->where('id', '!=', $dup->keep_id)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => now()]);
        }

        Schema::table('daily_checklists', function (Blueprint $table) {
            $table->dropUnique('daily_checklists_truck_date_unique');
        });

        Schema::table('daily_checklists', function (Blueprint $table) {
            $table->unique(['truck_id', 'week_start_date'], 'daily_checklists_truck_week_unique');
        });
    }

    public function down(): void
    {
        Schema::table('daily_checklists', function (Blueprint $table) {
            $table->dropUnique('daily_checklists_truck_week_unique');
        });

        Schema::table('daily_checklists', function (Blueprint $table) {
            $table->dropForeign(['validated_by']);
            $table->dropColumn(['week_start_date', 'status', 'validated_by', 'validated_at', 'validation_notes']);
            $table->unique(['truck_id', 'checklist_date'], 'daily_checklists_truck_date_unique');
        });
    }
};
