<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Parameters\FleetSettingParameterMap;
use App\Enums\OperationalParameterKey;
use App\Models\FleetSetting;
use App\Services\OperationalParameterService;
use Database\Seeders\OperationalParameterSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * R1.3 consolidation — the sync command copies live FleetSetting values into the
 * operational_parameters store so OperationalParameter becomes the source of truth
 * with zero behaviour change. Idempotent; dry-run never writes.
 */
class SyncOperationalParametersTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        (new OperationalParameterSeeder())->run();
        app(OperationalParameterService::class)->flush();
    }

    private function params(): OperationalParameterService
    {
        return app(OperationalParameterService::class);
    }

    public function test_sync_writes_live_value_into_parameter(): void
    {
        FleetSetting::current()->update(['default_capacity_tonnage' => 41]);
        $this->params()->set(OperationalParameterKey::DEFAULT_CAPACITY, 45); // diverged

        $this->artisan('operations:sync-parameters')->assertSuccessful();

        $this->assertSame(41.0, $this->params()->float(OperationalParameterKey::DEFAULT_CAPACITY));
    }

    public function test_dry_run_does_not_write(): void
    {
        FleetSetting::current()->update(['default_capacity_tonnage' => 41]);
        $this->params()->set(OperationalParameterKey::DEFAULT_CAPACITY, 99);

        $this->artisan('operations:sync-parameters', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(99.0, $this->params()->float(OperationalParameterKey::DEFAULT_CAPACITY));
    }

    public function test_sync_is_idempotent(): void
    {
        FleetSetting::current()->update(['default_capacity_tonnage' => 41]);

        $this->artisan('operations:sync-parameters')->assertSuccessful();
        $first = $this->params()->float(OperationalParameterKey::DEFAULT_CAPACITY);

        $this->artisan('operations:sync-parameters')->assertSuccessful();
        $second = $this->params()->float(OperationalParameterKey::DEFAULT_CAPACITY);

        $this->assertSame(41.0, $first);
        $this->assertSame($first, $second);
    }

    /** Byte-identical: after sync, every mapped parameter equals the live FleetSetting value. */
    public function test_after_sync_parameters_equal_fleetsetting(): void
    {
        FleetSetting::current()->update([
            'default_capacity_tonnage' => 41,
            'monthly_target_tonnage' => 3000,
        ]);

        $this->artisan('operations:sync-parameters')->assertSuccessful();

        $params = $this->params();
        $setting = FleetSetting::current();
        foreach (FleetSettingParameterMap::map() as $field => $key) {
            $this->assertEqualsWithDelta((float) $setting->{$field}, $params->float($key), 1e-9, $key->value);
        }
    }
}
