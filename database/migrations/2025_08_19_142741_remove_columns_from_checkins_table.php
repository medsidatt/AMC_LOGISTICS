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
        Schema::table('check_ins', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['project_id']);
            $table->dropForeign(['entity_id']);

            // Now drop the columns
            $table->dropColumn([
                'check_in_from',
                'project_id',
                'entity_id',
                'check_in_to',
            ]);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('check_ins', function (Blueprint $table) {

        });
    }
};
