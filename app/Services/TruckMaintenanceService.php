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
                $this->replaceMaintenanceProfileInterval(
                    $truck,
                    Maintenance::TYPE_GENERAL,
                    $intervalKm
                );
                $updated++;
            }
        });

        return $updated;
    }

    /**
     * Replace a maintenance rule by deactivating the old one and creating a new immutable rule.
     */
    public function replaceMaintenanceProfileInterval(
        Truck $truck,
        string $maintenanceType,
        float $intervalKm,
        ?float $warningThresholdKm = null
    ): void {
        $this->maintenanceStatusService->createRule(
            $truck,
            $maintenanceType,
            max(1, $intervalKm),
            $warningThresholdKm,
            auth()->id()
        );
    }
}
