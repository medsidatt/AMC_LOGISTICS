<?php

namespace Tests\Feature\Operations;

use App\Enums\OperationalParameterKey;
use App\Models\Auth\User;
use App\Models\FleetSetting;
use App\Services\OperationalParameterService;
use Database\Seeders\OperationalParameterSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * R1.3 consolidation — saving Fleet Settings must dual-write the same values into the
 * operational_parameters store, keeping FleetSetting and OperationalParameter identical.
 */
class FleetSettingsDualWriteTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        (new OperationalParameterSeeder())->run();
        app(OperationalParameterService::class)->flush();
    }

    public function test_updating_fleet_settings_dual_writes_parameters(): void
    {
        $user = User::query()->permission('fleet-settings-edit')->firstOrFail();

        $this->actingAs($user)
            ->put(route('settings.fleet.update'), [
                'default_capacity_tonnage' => 43,
                'target_rotations_per_week' => 4,
                'weight_gap_threshold' => 0.6,
                'price_per_litre' => 800,
                'change_note' => 'Dual-write characterization test',
            ])
            ->assertRedirect(route('settings.fleet.edit'));

        // FleetSetting updated (legacy compatibility)
        $setting = FleetSetting::current();
        $this->assertEqualsWithDelta(43.0, (float) $setting->default_capacity_tonnage, 1e-9);

        // Parameters updated identically (future source of truth)
        $params = app(OperationalParameterService::class);
        $this->assertSame(43.0, $params->float(OperationalParameterKey::DEFAULT_CAPACITY));
        $this->assertSame(4, $params->int(OperationalParameterKey::TARGET_ROTATIONS));
        $this->assertSame(0.6, $params->float(OperationalParameterKey::WEIGHT_OPERATIONAL_THRESHOLD));
        $this->assertSame(800.0, $params->float(OperationalParameterKey::PRICE_PER_LITRE));

        // Byte-identical: parameter == FleetSetting for every UI field
        $this->assertEqualsWithDelta((float) $setting->default_capacity_tonnage, $params->float(OperationalParameterKey::DEFAULT_CAPACITY), 1e-9);
        $this->assertEqualsWithDelta((float) $setting->weight_gap_threshold, $params->float(OperationalParameterKey::WEIGHT_OPERATIONAL_THRESHOLD), 1e-9);
    }
}
