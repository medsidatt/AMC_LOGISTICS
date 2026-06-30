<?php

namespace Tests\Feature;

use App\Models\OperationalParameter;
use App\Services\OperationalParameterService;
use Database\Seeders\OperationalParameterSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * R1.1 — Operational Parameters foundation.
 *
 * Characterization first: every seeded value must equal the value the platform
 * uses TODAY, so introducing the store changes no behaviour. Then the service
 * contract: typed getters, caching (one query per key), invalidation, fallback.
 *
 * DatabaseTransactions keeps the dev DB clean (no RefreshDatabase).
 */
class OperationalParameterServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed at current production values inside the rolled-back transaction.
        (new OperationalParameterSeeder())->run();
        app(OperationalParameterService::class)->flush();
    }

    private function svc(): OperationalParameterService
    {
        return app(OperationalParameterService::class);
    }

    /** Characterization: seeded values == the values in use today. */
    public function test_seeded_values_match_current_production_values(): void
    {
        $expected = [
            'default_capacity_tonnage' => 45.0,        // FleetSetting default (ADR-001)
            'capacity_buffer_ratio' => 0.15,           // FleetCapacityService::DEFAULT_BUFFER_RATIO
            'target_rotations_per_week' => 3,          // FleetSetting default
            'monthly_target_tonnage' => 0.0,           // FleetSetting default
            'cycle_time_hours' => 4.0,                 // FleetCapacityService cycle hours
            'weight_operational_threshold_t' => 0.5,   // FleetSetting.weight_gap_threshold
            'weight_fraud_threshold_kg' => 300.0,      // config maintenance.weight_gap_threshold_kg
            'weight_sensor_threshold_kg' => 150.0,     // config logistics.gap_threshold_kg
            'weight_anomaly_ratio' => 0.2,             // config logistics.weight_anomaly_threshold
            'price_per_litre' => 730.0,                // FleetSetting default
            'fiscal_month_start_day' => 22,            // dashboards' fiscal-month SQL
            'inspection_sla_days' => 30,               // dashboards' subDays(30)
            'max_rotations_before_maintenance' => 12,  // config logistics
            'max_km_before_maintenance' => 10000,      // config logistics
            'warning_threshold_km' => 500.0,           // config maintenance
        ];

        $svc = $this->svc();

        foreach ($expected as $key => $value) {
            if (is_int($value)) {
                $this->assertSame($value, $svc->int($key), "int {$key}");
            } else {
                $this->assertSame($value, $svc->float($key), "float {$key}");
            }
        }
    }

    /** Every seeded parameter carries the metadata the contract requires. */
    public function test_every_parameter_has_metadata(): void
    {
        $rows = OperationalParameter::query()->get();

        $this->assertGreaterThanOrEqual(15, $rows->count());

        foreach ($rows as $row) {
            $this->assertNotEmpty($row->key);
            $this->assertNotEmpty($row->type);
            $this->assertNotEmpty($row->category);
            $this->assertNotEmpty($row->description);
        }
    }

    public function test_typed_getters_return_native_types(): void
    {
        $svc = $this->svc();

        $this->assertIsInt($svc->int('fiscal_month_start_day'));
        $this->assertIsFloat($svc->float('weight_operational_threshold_t'));
        $this->assertIsString($svc->string('default_capacity_tonnage'));
    }

    /** Cached: a raw DB change (no model event) is NOT seen until the cache is flushed. */
    public function test_values_are_cached_until_flush(): void
    {
        $svc = $this->svc();
        $this->assertSame(45.0, $svc->float('default_capacity_tonnage'));

        // Bypass the model so the auto-invalidation does not fire.
        DB::table('operational_parameters')
            ->where('key', 'default_capacity_tonnage')
            ->update(['value' => '50']);

        $fresh = new OperationalParameterService();
        $this->assertSame(45.0, $fresh->float('default_capacity_tonnage'), 'still cached');

        $fresh->flush();
        $this->assertSame(50.0, $fresh->float('default_capacity_tonnage'), 'reloaded after flush');
    }

    /** set() persists and invalidates so the next read sees the new value. */
    public function test_set_updates_value_and_invalidates_cache(): void
    {
        $svc = $this->svc();
        $this->assertSame(3, $svc->int('target_rotations_per_week'));

        $svc->set('target_rotations_per_week', 4);

        $this->assertSame(4, $this->svc()->int('target_rotations_per_week'));
    }

    /** A model write also invalidates the cache (booted observer). */
    public function test_model_write_invalidates_cache(): void
    {
        $svc = $this->svc();
        $this->assertSame(0.5, $svc->float('weight_operational_threshold_t'));

        OperationalParameter::query()
            ->where('key', 'weight_operational_threshold_t')
            ->first()
            ->update(['value' => '0.7']);

        $this->assertSame(0.7, $this->svc()->float('weight_operational_threshold_t'));
    }

    public function test_missing_parameter_returns_single_fallback(): void
    {
        $svc = $this->svc();

        $this->assertNull($svc->get('does_not_exist'));
        $this->assertSame('fallback', $svc->get('does_not_exist', 'fallback'));
        $this->assertSame(99.0, $svc->float('does_not_exist', 99.0));
        $this->assertSame(7, $svc->int('does_not_exist', 7));
    }

    public function test_set_rejects_unknown_parameter(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->svc()->set('not_a_real_key', 1);
    }
}
