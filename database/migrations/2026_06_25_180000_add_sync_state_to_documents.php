<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('documents', 'sync_status')) return;

        Schema::table('documents', function (Blueprint $table) {
            // Default 'synced' so every existing row and every still-inline upload
            // (driver/maintenance/inspection docs) is correct without a backfill.
            // The new local-first transport-tracking flow sets 'pending' explicitly.
            $table->string('sync_status', 16)->default('synced')->after('sharepoint_url');
            $table->timestamp('synced_at')->nullable()->after('sync_status');
            $table->text('last_sync_error')->nullable()->after('synced_at');
            $table->unsignedInteger('retry_count')->default(0)->after('last_sync_error');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['sync_status', 'synced_at', 'last_sync_error', 'retry_count']);
        });
    }
};
