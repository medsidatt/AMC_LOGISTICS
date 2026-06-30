<?php

namespace Tests\Feature;

use App\Enums\OperationalParameterKey;
use App\Enums\ParameterOwner;
use App\Exceptions\MissingOperationalParameterException;
use App\Models\OperationalParameter;
use App\Services\OperationalParameterService;
use Database\Seeders\OperationalParameterSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * R1.1 — Operational Parameters foundation (hardened per ADR-008).
 *
 * Characterization first: every seeded value must equal the value the platform
 * uses TODAY. Then the contract: typed getters, NO business defaults in the
 * service, enum-keyed access, caching, invalidation, and seeder validation.
 *
 * DatabaseTransactions keeps the dev DB clean (no RefreshDatabase).
 */
class OperationalParameterServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
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
            'default_capacity_tonnage' => 45.0,
            'capacity_buffer_ratio' => 0.15,
            'target_rotations_per_week' => 3,
            'monthly_target_tonnage' => 0.0,
            'cycle_time_hours' => 4.0,
            'weight_operational_threshold_t' => 0.5,
            'weight_fraud_threshold_kg' => 300.0,
            'weight_sensor_threshold_kg' => 150.0,
            'weight_anomaly_ratio' => 0.2,
            'price_per_litre' => 730.0,
            'fiscal_month_start_day' => 22,
            'inspection_sla_days' => 30,
            'max_rotations_before_maintenance' => 12,
            'max_km_before_maintenance' => 10000,
            'warning_threshold_km' => 500.0,
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

    /** Every enum key is seeded, and every row carries the required metadata. */
    public function test_every_enum_key_is_seeded_with_metadata(): void
    {
        $svc = $this->svc();

        foreach (OperationalParameterKey::cases() as $case) {
            $this->assertTrue($svc->has($case), "missing seed for {$case->value}");
        }

        foreach (OperationalParameter::query()->get() as $row) {
            $this->assertNotEmpty($row->key);
            $this->assertNotEmpty($row->type);
            $this->assertNotEmpty($row->category);
            $this->assertNotEmpty($row->owner);
            $this->assertNotEmpty($row->description);
        }
    }

    public function test_typed_getters_return_native_types_and_accept_enum_keys(): void
    {
        $svc = $this->svc();

        $this->assertIsInt($svc->int(OperationalParameterKey::FISCAL_MONTH_START_DAY));
        $this->assertIsFloat($svc->float(OperationalParameterKey::WEIGHT_OPERATIONAL_THRESHOLD));
        $this->assertIsString($svc->string(OperationalParameterKey::DEFAULT_CAPACITY));
        $this->assertSame(45.0, $svc->float(OperationalParameterKey::DEFAULT_CAPACITY));
    }

    public function test_enum_getter_resolves_backed_enum(): void
    {
        OperationalParameter::query()->create([
            'key' => 'tmp_owner_enum', 'value' => 'fleet', 'type' => 'string',
            'unit' => null, 'category' => 'capacity', 'owner' => 'fleet',
            'description' => 'temp', 'is_active' => true,
        ]);
        $svc = $this->svc();
        $svc->flush();

        $this->assertSame(ParameterOwner::FLEET, $svc->enum('tmp_owner_enum', ParameterOwner::class));
    }

    // --- No defaults in the service (ADR-008) ------------------------------

    public function test_missing_key_throws_and_has_reports_false(): void
    {
        $svc = $this->svc();

        $this->assertFalse($svc->has('does_not_exist'));
        $this->expectException(MissingOperationalParameterException::class);
        $svc->float('does_not_exist');
    }

    public function test_enum_getter_rejects_value_outside_enum(): void
    {
        $svc = $this->svc();
        // weight_anomaly_ratio = "0.2" is not a ParameterOwner case.
        $this->expectException(\ValueError::class);
        $svc->enum(OperationalParameterKey::WEIGHT_ANOMALY_RATIO, ParameterOwner::class);
    }

    // --- Cache -------------------------------------------------------------

    /** Cache warm: a raw DB change (no model event) is NOT seen until flush. */
    public function test_cache_is_warm_until_flush(): void
    {
        $svc = $this->svc();
        $this->assertSame(45.0, $svc->float(OperationalParameterKey::DEFAULT_CAPACITY));

        DB::table('operational_parameters')
            ->where('key', 'default_capacity_tonnage')
            ->update(['value' => '50']);

        $fresh = new OperationalParameterService();
        $this->assertSame(45.0, $fresh->float(OperationalParameterKey::DEFAULT_CAPACITY), 'still cached');

        $fresh->flush();
        $this->assertSame(50.0, $fresh->float(OperationalParameterKey::DEFAULT_CAPACITY), 'reloaded after flush');
    }

    public function test_set_updates_value_and_refreshes_cache(): void
    {
        $svc = $this->svc();
        $this->assertSame(3, $svc->int(OperationalParameterKey::TARGET_ROTATIONS));

        $svc->set(OperationalParameterKey::TARGET_ROTATIONS, 4);

        $this->assertSame(4, $this->svc()->int(OperationalParameterKey::TARGET_ROTATIONS));
    }

    public function test_model_write_invalidates_cache(): void
    {
        $svc = $this->svc();
        $this->assertSame(0.5, $svc->float(OperationalParameterKey::WEIGHT_OPERATIONAL_THRESHOLD));

        OperationalParameter::query()
            ->where('key', 'weight_operational_threshold_t')
            ->first()
            ->update(['value' => '0.7']);

        $this->assertSame(0.7, $this->svc()->float(OperationalParameterKey::WEIGHT_OPERATIONAL_THRESHOLD));
    }

    /** Concurrent update: one instance writes; another (fresh request) sees it via the shared cache. */
    public function test_concurrent_update_is_visible_to_other_instances(): void
    {
        $reader = new OperationalParameterService();
        $this->assertSame(45.0, $reader->float(OperationalParameterKey::DEFAULT_CAPACITY)); // warms shared cache

        $writer = new OperationalParameterService();
        $writer->set(OperationalParameterKey::DEFAULT_CAPACITY, 48); // saves + forgets shared cache

        $nextRequest = new OperationalParameterService(); // fresh memo, shared cache was cleared
        $this->assertSame(48.0, $nextRequest->float(OperationalParameterKey::DEFAULT_CAPACITY));
    }

    public function test_set_rejects_unknown_parameter(): void
    {
        $this->expectException(MissingOperationalParameterException::class);
        $this->svc()->set('not_a_real_key', 1);
    }

    // --- Seeder validation (first line of defence) -------------------------

    public function test_validator_accepts_the_real_seed(): void
    {
        OperationalParameterSeeder::validateRows([
            ['key' => 'k', 'value' => '1', 'type' => 'int', 'unit' => 'days', 'category' => 'inspection', 'owner' => 'hse'],
        ]);
        $this->assertTrue(true); // no exception
    }

    public function test_validator_rejects_duplicate_key(): void
    {
        $this->expectException(RuntimeException::class);
        OperationalParameterSeeder::validateRows([
            ['key' => 'dup', 'value' => '1', 'type' => 'int', 'unit' => 'days', 'category' => 'inspection', 'owner' => 'hse'],
            ['key' => 'dup', 'value' => '2', 'type' => 'int', 'unit' => 'days', 'category' => 'inspection', 'owner' => 'hse'],
        ]);
    }

    public function test_validator_rejects_unknown_category(): void
    {
        $this->expectException(RuntimeException::class);
        OperationalParameterSeeder::validateRows([
            ['key' => 'k', 'value' => '1', 'type' => 'int', 'unit' => 'days', 'category' => 'nonsense', 'owner' => 'hse'],
        ]);
    }

    public function test_validator_rejects_unknown_unit(): void
    {
        $this->expectException(RuntimeException::class);
        OperationalParameterSeeder::validateRows([
            ['key' => 'k', 'value' => '1', 'type' => 'int', 'unit' => 'parsecs', 'category' => 'inspection', 'owner' => 'hse'],
        ]);
    }

    public function test_validator_rejects_value_not_matching_type(): void
    {
        $this->expectException(RuntimeException::class);
        OperationalParameterSeeder::validateRows([
            ['key' => 'k', 'value' => 'abc', 'type' => 'int', 'unit' => 'days', 'category' => 'inspection', 'owner' => 'hse'],
        ]);
    }

    /** Duplicate seed run is idempotent — no duplicate rows. */
    public function test_reseeding_is_idempotent(): void
    {
        $before = OperationalParameter::query()->count();
        (new OperationalParameterSeeder())->run();
        $this->assertSame($before, OperationalParameter::query()->count());
    }
}
