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
        Schema::table('projects', function (Blueprint $table) {
            // Drop old unique indexes (adjust names if different in DB)
            $table->dropUnique('projects_code_unique');
            $table->dropUnique('projects_name_unique');

            // Add new unique constraints scoped by entity_id
            $table->unique(['code', 'entity_id'], 'projects_code_entity_unique');
            $table->unique(['name', 'entity_id'], 'projects_name_entity_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Drop composite indexes
            $table->dropUnique('projects_code_entity_unique');
            $table->dropUnique('projects_name_entity_unique');

            // Restore old ones
            $table->unique('code', 'projects_code_unique');
            $table->unique('name', 'projects_name_unique');
        });
    }
};
