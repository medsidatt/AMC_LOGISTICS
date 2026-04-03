<?php

use App\Models\Employee\Employee;
use App\Models\Entity;
use App\Models\Payroll;
use App\Models\Project;
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
            $table->foreignIdFor(Entity::class)
                ->nullable()
                ->after('check_in_from')
                ->constrained()
                ->nullOnDelete();
            $table->foreignIdFor(Project::class)
                ->nullable()
                ->after('check_in_from')
                ->constrained()
                ->nullOnDelete();
            // payroll_id
            $table->foreignIdFor(Payroll::class)
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('check_ins', function (Blueprint $table) {
            $table->dropForeign(['entity_id']);
            $table->dropColumn('entity_id');
            $table->dropForeign(['project_id']);
            $table->dropColumn('project_id');
            $table->dropForeign(['payroll_id']);
            $table->dropColumn('payroll_id');
        });
    }
};
