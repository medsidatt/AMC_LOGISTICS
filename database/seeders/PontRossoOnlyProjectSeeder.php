<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-shot seeder: wipes ALL projects (including soft-deleted) and inserts
 * a single "Pont Rosso" project. Use only when consolidating to a single
 * active project. Foreign keys with onDelete('set null') will null out
 * (inspection_checklists, truck_assignments, client_demand_plans, etc.).
 */
class PontRossoOnlyProjectSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // Detach from the project_user pivot first to avoid FK issues.
            if (Schema::hasTable('project_user')) {
                DB::table('project_user')->delete();
            }

            // Force-delete every project row, soft-deleted included.
            Project::withTrashed()->forceDelete();

            // Reset the auto-increment so the new project gets id = 1.
            DB::statement('ALTER TABLE projects AUTO_INCREMENT = 1');

            Project::create([
                'name' => 'Pont Rosso',
                'code' => 'PONT-ROSSO',
                'description' => 'Construction du Pont de Rosso — chantier unique en cours.',
                'is_active' => true,
            ]);
        });
    }
}
