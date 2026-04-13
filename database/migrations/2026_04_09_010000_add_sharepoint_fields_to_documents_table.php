<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('documents', 'sharepoint_url')) return;

        Schema::table('documents', function (Blueprint $table) {
            $table->string('sharepoint_id')->nullable()->after('size');
            $table->text('sharepoint_url')->nullable()->after('sharepoint_id');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['sharepoint_id', 'sharepoint_url']);
        });
    }
};
