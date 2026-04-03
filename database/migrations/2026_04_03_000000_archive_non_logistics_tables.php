<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables to archive by renaming with 'archived_' prefix.
     * This preserves all data while removing them from active use.
     */
    private array $tables = [
        // HR / Employees
        'employees',
        'contracts',
        'contract_types',
        'explanation_requests',
        'explanation_request_responses',
        'specialties',
        'employee_component',

        // Payroll
        'payrolls',
        'payroll_details',
        'payroll_components',
        'salaries',
        'salary_components',
        'salary_grids',
        'components',
        'project_component',
        'adjustments',
        'pay_splits',
        'pay_split_details',
        'configuration_rules',
        'rubrique_masters',
        'perks',

        // Attendance / Leaves
        'check_ins',
        'check_in_details',
        'check_in_types',
        'leaves',

        // Organization (unused)
        'departments',
        'services',
        'job_titles',
        'job_title_categories',
        'job_title_classes',

        // PPE / Inventory
        'brands',
        'categories',
        'products',
        'product_variables',

        // CMS
        'blogs',
        'posts',

        // Humanitarian / Financial
        'donors',
        'beneficiaries',
        'bank_accounts',
        'currencies',
        'forms',
        'banks',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && !Schema::hasTable("archived_{$table}")) {
                Schema::rename($table, "archived_{$table}");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable("archived_{$table}") && !Schema::hasTable($table)) {
                Schema::rename("archived_{$table}", $table);
            }
        }
    }
};
