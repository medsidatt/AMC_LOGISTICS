<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->constrained('projects')->onDelete('cascade')->after('id');
            $table->foreignId('entity_id')->nullable()->constrained('entities')->onDelete('cascade')->after('project_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropForeign(['entity_id']);
            $table->dropColumn(['project_id', 'entity_id']);
        });
    }
};
