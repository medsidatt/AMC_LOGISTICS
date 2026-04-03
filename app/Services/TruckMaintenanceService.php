<?php

namespace App\Services;

use App\Models\Maintenance;
use App\Models\Truck;

class TruckMaintenanceService
{
    public function __construct(
        private readonly MaintenanceStatusService $maintenanceStatusService
    ) {
    }

    public function updateMaintenanceType(Truck $truck, string $maintenanceType): void
    {
        $truck->update(['maintenance_type' => $maintenanceType]);
        $this->maintenanceStatusService->recalculateForTruck($truck->fresh());
    }

    public function bulkUpdateMaintenanceType(string $maintenanceType): int
    {
        $updated = Truck::query()->update(['maintenance_type' => $maintenanceType]);
        Truck::query()->get()->each(fn (Truck $truck) => $this->maintenanceStatusService->recalculateForTruck($truck));

        return $updated;
    }

    public function bulkUpdateKmInterval(float $intervalKm): int
    {
        $updated = 0;

        Truck::query()->chunkById(100, function ($trucks) use ($intervalKm, &$updated) {
            foreach ($trucks as $truck) {
                $this->updateMaintenanceProfileInterval(
                    $truck,
                    Maintenance::TYPE_GENERAL,
                    $intervalKm
                );
                $updated++;
            }
        });

        return $updated;
    }

    public function updateMaintenanceProfileInterval(
        Truck $truck,
        string $maintenanceType,
        float $intervalKm,
        ?float $warningThresholdKm = null
    ): void {
        $profile = $this->maintenanceStatusService->ensureProfile($truck, $maintenanceType);
        $profile->update([
            'interval_km' => max(1, $intervalKm),
            'warning_threshold_km' => is_null($warningThresholdKm) ? $profile->warning_threshold_km : max(0, $warningThresholdKm),
        ]);

        if ($maintenanceType === 'general') {
            $truck->update(['km_maintenance_interval' => max(1, $intervalKm)]);
        }

        $this->maintenanceStatusService->recalculateProfile($truck->fresh(), $profile->fresh());
    }
}
