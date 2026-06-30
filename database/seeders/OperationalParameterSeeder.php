<?php

namespace Database\Seeders;

use App\Enums\OperationalParameterKey;
use App\Enums\ParameterCategory;
use App\Enums\ParameterOwner;
use App\Enums\ParameterUnit;
use App\Models\OperationalParameter;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * R1.1 — seeds the operational parameters at their CURRENT production values.
 *
 * The seeder is the first line of defence (ADR-008): it validates every row for a
 * unique key, a known category/unit/owner, and a value that matches its type, and
 * fails immediately on any inconsistency.
 *
 * Each value is the one the platform already uses today, so behaviour is unchanged.
 * firstOrCreate keys on `key`: re-running never clobbers an operator-tuned value.
 * Parameters with no current source (objective/pace/productivity/utilization bands,
 * billable_window_days, start_deadline_hours, revenue_rate_per_tonne, maintenance
 * warning %) are intentionally NOT seeded here — they arrive with their KPI's
 * calculator in a later phase, to avoid inventing values now.
 */
class OperationalParameterSeeder extends Seeder
{
    private const VALID_TYPES = ['int', 'float', 'bool', 'string', 'json'];

    public function run(): void
    {
        $rows = $this->parameters();
        self::validateRows($rows);

        foreach ($rows as $p) {
            OperationalParameter::query()->firstOrCreate(
                ['key' => $p['key']],
                [
                    'value' => $p['value'],
                    'type' => $p['type'],
                    'unit' => $p['unit'],
                    'category' => $p['category'],
                    'owner' => $p['owner'],
                    'description' => $p['description'],
                    'is_active' => true,
                    'editable' => $p['editable'] ?? true,
                    'deprecated' => $p['deprecated'] ?? false,
                    'introduced_by_adr' => $p['introduced_by_adr'] ?? null,
                    'notes' => $p['notes'] ?? null,
                ],
            );
        }
    }

    /**
     * Fail fast on any inconsistency: duplicate key, unknown category/unit/owner,
     * invalid type, or a value that does not match its declared type.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function validateRows(array $rows): void
    {
        $seen = [];

        foreach ($rows as $p) {
            $key = $p['key'] ?? '(missing key)';

            if (isset($seen[$key])) {
                throw new RuntimeException("Duplicate operational parameter key: {$key}");
            }
            $seen[$key] = true;

            if (! in_array($p['type'] ?? null, self::VALID_TYPES, true)) {
                throw new RuntimeException("Invalid type for {$key}: ".($p['type'] ?? 'null'));
            }
            if (ParameterCategory::tryFrom($p['category'] ?? '') === null) {
                throw new RuntimeException("Unknown category for {$key}: ".($p['category'] ?? 'null'));
            }
            if (ParameterUnit::tryFrom($p['unit'] ?? '') === null) {
                throw new RuntimeException("Unknown unit for {$key}: ".($p['unit'] ?? 'null'));
            }
            if (ParameterOwner::tryFrom($p['owner'] ?? '') === null) {
                throw new RuntimeException("Unknown owner for {$key}: ".($p['owner'] ?? 'null'));
            }
            if (! self::valueMatchesType((string) ($p['value'] ?? ''), $p['type'])) {
                throw new RuntimeException("Value does not match type {$p['type']} for {$key}: ".($p['value'] ?? 'null'));
            }
        }
    }

    private static function valueMatchesType(string $value, string $type): bool
    {
        return match ($type) {
            'int' => preg_match('/^-?\d+$/', $value) === 1,
            'float' => is_numeric($value),
            'bool' => in_array(strtolower($value), ['0', '1', 'true', 'false'], true),
            'json' => json_decode($value) !== null || $value === 'null',
            'string' => true,
            default => false,
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parameters(): array
    {
        $c = fn (ParameterCategory $x) => $x->value;
        $u = fn (ParameterUnit $x) => $x->value;
        $o = fn (ParameterOwner $x) => $x->value;
        $k = fn (OperationalParameterKey $x) => $x->value;

        return [
            // --- Capacity -------------------------------------------------
            [
                'key' => $k(OperationalParameterKey::DEFAULT_CAPACITY), 'value' => '45', 'type' => 'float',
                'unit' => $u(ParameterUnit::TONNES), 'category' => $c(ParameterCategory::CAPACITY), 'owner' => $o(ParameterOwner::FLEET),
                'description' => 'Default load a truck carries when no per-truck capacity is set.',
                'introduced_by_adr' => 'ADR-001',
            ],
            [
                'key' => $k(OperationalParameterKey::CAPACITY_BUFFER_RATIO), 'value' => '0.15', 'type' => 'float',
                'unit' => $u(ParameterUnit::RATIO), 'category' => $c(ParameterCategory::CAPACITY), 'owner' => $o(ParameterOwner::FLEET),
                'description' => 'Share of working time held back as a buffer when estimating capacity.',
            ],

            // --- Rotations & objective -----------------------------------
            [
                'key' => $k(OperationalParameterKey::TARGET_ROTATIONS), 'value' => '3', 'type' => 'int',
                'unit' => $u(ParameterUnit::ROTATIONS_PER_WEEK), 'category' => $c(ParameterCategory::ROTATIONS), 'owner' => $o(ParameterOwner::OPERATIONS),
                'description' => 'Target number of round trips each truck should complete per week.',
                'notes' => 'Global default; individual trucks may override this on their own record.',
            ],
            [
                'key' => $k(OperationalParameterKey::MONTHLY_TARGET_TONNAGE), 'value' => '0', 'type' => 'float',
                'unit' => $u(ParameterUnit::TONNES), 'category' => $c(ParameterCategory::OBJECTIVE), 'owner' => $o(ParameterOwner::OPERATIONS),
                'description' => 'Monthly tonnage objective for the operation.',
            ],
            [
                'key' => $k(OperationalParameterKey::CYCLE_TIME_HOURS), 'value' => '4', 'type' => 'float',
                'unit' => $u(ParameterUnit::HOURS), 'category' => $c(ParameterCategory::CYCLE), 'owner' => $o(ParameterOwner::OPERATIONS),
                'description' => 'Expected hours for one full load cycle: load, deliver and return.',
            ],

            // --- Weight thresholds (three independent — ADR-002) ----------
            [
                'key' => $k(OperationalParameterKey::WEIGHT_OPERATIONAL_THRESHOLD), 'value' => '0.5', 'type' => 'float',
                'unit' => $u(ParameterUnit::TONNES), 'category' => $c(ParameterCategory::WEIGHT), 'owner' => $o(ParameterOwner::OPERATIONS),
                'description' => 'Daily load gap above which a weighing anomaly is flagged for review.',
                'introduced_by_adr' => 'ADR-002',
            ],
            [
                'key' => $k(OperationalParameterKey::WEIGHT_FRAUD_THRESHOLD), 'value' => '300', 'type' => 'float',
                'unit' => $u(ParameterUnit::KILOGRAMS), 'category' => $c(ParameterCategory::WEIGHT), 'owner' => $o(ParameterOwner::EXECUTIVE),
                'description' => 'Load gap above which a possible theft is opened for investigation.',
                'introduced_by_adr' => 'ADR-002',
            ],
            [
                'key' => $k(OperationalParameterKey::WEIGHT_SENSOR_THRESHOLD), 'value' => '150', 'type' => 'float',
                'unit' => $u(ParameterUnit::KILOGRAMS), 'category' => $c(ParameterCategory::WEIGHT), 'owner' => $o(ParameterOwner::FLEET),
                'description' => 'Load gap small enough to be explained by weighbridge or sensor tolerance.',
                'introduced_by_adr' => 'ADR-002',
            ],
            [
                'key' => $k(OperationalParameterKey::WEIGHT_ANOMALY_RATIO), 'value' => '0.2', 'type' => 'float',
                'unit' => $u(ParameterUnit::RATIO), 'category' => $c(ParameterCategory::WEIGHT), 'owner' => $o(ParameterOwner::OPERATIONS),
                'description' => 'Proportional load gap that marks a delivery as anomalous.',
            ],

            // --- Finance --------------------------------------------------
            [
                'key' => $k(OperationalParameterKey::PRICE_PER_LITRE), 'value' => '730', 'type' => 'float',
                'unit' => $u(ParameterUnit::CURRENCY_PER_LITRE), 'category' => $c(ParameterCategory::FINANCE), 'owner' => $o(ParameterOwner::FINANCE),
                'description' => 'Reference fuel price used to value consumption.',
            ],

            // --- Fiscal calendar -----------------------------------------
            [
                'key' => $k(OperationalParameterKey::FISCAL_MONTH_START_DAY), 'value' => '22', 'type' => 'int',
                'unit' => $u(ParameterUnit::DAY_OF_MONTH), 'category' => $c(ParameterCategory::FISCAL), 'owner' => $o(ParameterOwner::FINANCE),
                'description' => 'Day of the month on which the operational/billing month begins.',
            ],

            // --- Inspection ----------------------------------------------
            [
                'key' => $k(OperationalParameterKey::INSPECTION_SLA_DAYS), 'value' => '30', 'type' => 'int',
                'unit' => $u(ParameterUnit::DAYS), 'category' => $c(ParameterCategory::INSPECTION), 'owner' => $o(ParameterOwner::HSE),
                'description' => 'Number of days an inspection stays valid before it must be renewed.',
            ],

            // --- Maintenance ---------------------------------------------
            [
                'key' => $k(OperationalParameterKey::MAX_ROTATIONS_BEFORE_MAINTENANCE), 'value' => '12', 'type' => 'int',
                'unit' => $u(ParameterUnit::ROTATIONS), 'category' => $c(ParameterCategory::MAINTENANCE), 'owner' => $o(ParameterOwner::MAINTENANCE),
                'description' => 'Round trips a truck may run before maintenance becomes due.',
            ],
            [
                'key' => $k(OperationalParameterKey::MAX_KM_BEFORE_MAINTENANCE), 'value' => '10000', 'type' => 'int',
                'unit' => $u(ParameterUnit::KILOMETRES), 'category' => $c(ParameterCategory::MAINTENANCE), 'owner' => $o(ParameterOwner::MAINTENANCE),
                'description' => 'Kilometres a truck may run before maintenance becomes due.',
            ],
            [
                'key' => $k(OperationalParameterKey::WARNING_THRESHOLD_KM), 'value' => '500', 'type' => 'float',
                'unit' => $u(ParameterUnit::KILOMETRES), 'category' => $c(ParameterCategory::MAINTENANCE), 'owner' => $o(ParameterOwner::MAINTENANCE),
                'description' => 'Remaining kilometres at which a maintenance warning is raised.',
            ],
            [
                'key' => $k(OperationalParameterKey::MAINTENANCE_WARNING_RATIO), 'value' => '0.1', 'type' => 'float',
                'unit' => $u(ParameterUnit::RATIO), 'category' => $c(ParameterCategory::MAINTENANCE), 'owner' => $o(ParameterOwner::MAINTENANCE),
                'description' => 'Share of the maintenance interval remaining below which a truck is flagged as a warning.',
            ],
        ];
    }
}
