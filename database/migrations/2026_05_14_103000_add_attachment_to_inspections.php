<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_checklists', function (Blueprint $table) {
            $table->string('attachment_path')->nullable()->after('immatriculation_visible');
            $table->text('attachment_url')->nullable()->after('attachment_path');
            $table->string('attachment_filename')->nullable()->after('attachment_url');
        });
    }

    public function down(): void
    {
        Schema::table('inspection_checklists', function (Blueprint $table) {
            $table->dropColumn(['attachment_path', 'attachment_url', 'attachment_filename']);
        });
    }
};
