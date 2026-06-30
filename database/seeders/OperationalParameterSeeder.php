<?php

namespace Database\Seeders;

use App\Models\OperationalParameter;
use Illuminate\Database\Seeder;

/**
 * R1.1 — seeds the operational parameters at their CURRENT production values.
 *
 * Each value is the one the platform already uses today (FleetSetting defaults,
 * config files, or the literals found in the audit) so behaviour is unchanged.
 * The only frozen decision applied is ADR-001 (default capacity = 45 t), which
 * already matches the live FleetSetting default.
 *
 * firstOrCreate keys on `key`: re-running never clobbers an operator-tuned value.
 * Parameters with no current source (objective/pace/productivity/utilization bands,
 * billable_window_days, start_deadline_hours, revenue_rate_per_tonne, maintenance
 * warning %) are intentionally NOT seeded here — they are introduced with their
 * KPI's calculator in a later phase, to avoid inventing values now.
 */
class OperationalParameterSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->parameters() as $p) {
            OperationalParameter::query()->firstOrCreate(
                ['key' => $p['key']],
                [
                    'value' => $p['value'],
                    'type' => $p['type'],
                    'unit' => $p['unit'],
                    'category' => $p['category'],
                    'description' => $p['description'],
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * @return array<int, array{key:string,value:string,type:string,unit:?string,category:string,description:string}>
     */
    private function parameters(): array
    {
        return [
            // --- Capacity -------------------------------------------------
            [
                'key' => 'default_capacity_tonnage', 'value' => '45', 'type' => 'float',
                'unit' => 'tonnes', 'category' => 'capacity',
                'description' => 'Default load a truck carries when no per-truck capacity is set.',
            ],
            [
                'key' => 'capacity_buffer_ratio', 'value' => '0.15', 'type' => 'float',
                'unit' => 'ratio', 'category' => 'capacity',
                'description' => 'Share of working time held back as a buffer when estimating capacity.',
            ],

            // --- Rotations & objective -----------------------------------
            [
                'key' => 'target_rotations_per_week', 'value' => '3', 'type' => 'int',
                'unit' => 'rotations/week', 'category' => 'rotations',
                'description' => 'Target number of round trips each truck should complete per week.',
            ],
            [
                'key' => 'monthly_target_tonnage', 'value' => '0', 'type' => 'float',
                'unit' => 'tonnes', 'category' => 'objective',
                'description' => 'Monthly tonnage objective for the operation.',
            ],
            [
                'key' => 'cycle_time_hours', 'value' => '4', 'type' => 'float',
                'unit' => 'hours', 'category' => 'cycle',
                'description' => 'Expected hours for one full load cycle: load, deliver and return.',
            ],

            // --- Weight thresholds (three independent — ADR-002) ----------
            [
                'key' => 'weight_operational_threshold_t', 'value' => '0.5', 'type' => 'float',
                'unit' => 'tonnes', 'category' => 'weight',
                'description' => 'Daily load gap above which a weighing anomaly is flagged for review.',
            ],
            [
                'key' => 'weight_fraud_threshold_kg', 'value' => '300', 'type' => 'float',
                'unit' => 'kg', 'category' => 'weight',
                'description' => 'Load gap above which a possible theft is opened for investigation.',
            ],
            [
                'key' => 'weight_sensor_threshold_kg', 'value' => '150', 'type' => 'float',
                'unit' => 'kg', 'category' => 'weight',
                'description' => 'Load gap small enough to be explained by weighbridge or sensor tolerance.',
            ],
            [
                'key' => 'weight_anomaly_ratio', 'value' => '0.2', 'type' => 'float',
                'unit' => 'ratio', 'category' => 'weight',
                'description' => 'Proportional load gap that marks a delivery as anomalous.',
            ],

            // --- Finance --------------------------------------------------
            [
                'key' => 'price_per_litre', 'value' => '730', 'type' => 'float',
                'unit' => 'currency/litre', 'category' => 'finance',
                'description' => 'Reference fuel price used to value consumption.',
            ],

            // --- Fiscal calendar -----------------------------------------
            [
                'key' => 'fiscal_month_start_day', 'value' => '22', 'type' => 'int',
                'unit' => 'day-of-month', 'category' => 'fiscal',
                'description' => 'Day of the month on which the operational/billing month begins.',
            ],

            // --- Inspection ----------------------------------------------
            [
                'key' => 'inspection_sla_days', 'value' => '30', 'type' => 'int',
                'unit' => 'days', 'category' => 'inspection',
                'description' => 'Number of days an inspection stays valid before it must be renewed.',
            ],

            // --- Maintenance ---------------------------------------------
            [
                'key' => 'max_rotations_before_maintenance', 'value' => '12', 'type' => 'int',
                'unit' => 'rotations', 'category' => 'maintenance',
                'description' => 'Round trips a truck may run before maintenance becomes due.',
            ],
            [
                'key' => 'max_km_before_maintenance', 'value' => '10000', 'type' => 'int',
                'unit' => 'km', 'category' => 'maintenance',
                'description' => 'Kilometres a truck may run before maintenance becomes due.',
            ],
            [
                'key' => 'warning_threshold_km', 'value' => '500', 'type' => 'float',
                'unit' => 'km', 'category' => 'maintenance',
                'description' => 'Remaining kilometres at which a maintenance warning is raised.',
            ],
        ];
    }
}
