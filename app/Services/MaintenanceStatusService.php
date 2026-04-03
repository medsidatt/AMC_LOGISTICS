<?php

namespace App\Services;

use App\Models\Maintenance;
use App\Models\Truck;
use App\Models\TruckMaintenanceProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class MaintenanceStatusService
{
    public function recalculateForTruck(Truck $truck): Collection
    {
        if (! Schema::hasTable('truck_maintenance_profiles')) {
            return collect();
        }

        $profiles = $this->ensureProfiles($truck);

        return $profiles->map(function (TruckMaintenanceProfile $profile) use ($truck) {
            return $this->recalculateProfile($truck, $profile);
        });
    }

    public function recalculateProfile(Truck $truck, TruckMaintenanceProfile $profile): TruckMaintenanceProfile
    {
        if (! Schema::hasTable('truck_maintenance_profiles')) {
            return $profile;
        }

        $lastMaintenance = $truck->maintenances()
            ->where('maintenance_type', $profile->maintenance_type)
            ->latest('maintenance_date')
            ->first();

        $lastMaintenanceKm = $lastMaintenance?->kilometers_at_maintenance;
        if (is_null($lastMaintenanceKm)) {
            $lastMaintenanceKm = 0;
        }

        $intervalKm = max(1, (float) $profile->interval_km);
        $nextMaintenanceKm = (float) $lastMaintenanceKm + $intervalKm;
        $remaining = max(0.0, $nextMaintenanceKm - (float) $truck->total_kilometers);
        $warningThreshold = $this->warningThreshold($profile);

        $profile->update([
            'last_maintenance_km' => $lastMaintenanceKm,
            'next_maintenance_km' => $nextMaintenanceKm,
            'status' => $this->statusFromRemaining($remaining, $warningThreshold),
            'last_calculated_at' => now(),
        ]);

        return $profile->refresh();
    }

    public function recordMaintenance(
        Truck $truck,
        string $date,
        ?string $notes = null,
        string $maintenanceType = 'general',
        ?float $kilometersAtMaintenance = null
    ): Maintenance|false {
        if (! Schema::hasTable('truck_maintenance_profiles')) {
            return $truck->maintenances()->create([
                'maintenance_date' => $date,
                'notes' => $notes,
                'kilometers_at_maintenance' => $kilometersAtMaintenance ?? $truck->total_kilometers,
            ]);
        }

        if ($truck->maintenances()
            ->whereDate('maintenance_date', $date)
            ->where('maintenance_type', $maintenanceType)
            ->exists()) {
            return false;
        }

        $maintenance = $truck->maintenances()->create([
            'maintenance_date' => $date,
            'maintenance_type' => $maintenanceType,
            'notes' => $notes,
            'kilometers_at_maintenance' => $kilometersAtMaintenance ?? $truck->total_kilometers,
        ]);

        $profile = $this->ensureProfile($truck, $maintenanceType);
        $this->recalculateProfile($truck, $profile);

        return $maintenance;
    }

    public function warningThreshold(?TruckMaintenanceProfile $profile = null): float
    {
        if ($profile && ! is_null($profile->warning_threshold_km)) {
            return max(0.0, (float) $profile->warning_threshold_km);
        }

        return max(0.0, (float) config('maintenance.warning_threshold_km', 500));
    }

    public function statusFromRemaining(float $remainingKm, float $warningThresholdKm): string
    {
        if ($remainingKm <= 0) {
            return 'red';
        }

        if ($remainingKm <= $warningThresholdKm) {
            return 'yellow';
        }

        return 'green';
    }

    public function ensureProfile(Truck $truck, string $maintenanceType): TruckMaintenanceProfile
    {
        if (! Schema::hasTable('truck_maintenance_profiles')) {
            throw new \RuntimeException('truck_maintenance_profiles table is missing. Run migrations.');
        }

        $types = (array) config('maintenance.types', []);
        $defaultInterval = data_get($types, $maintenanceType.'.default_interval_km', 10000);

        return TruckMaintenanceProfile::firstOrCreate(
            [
                'truck_id' => $truck->id,
                'maintenance_type' => $maintenanceType,
            ],
            [
                'interval_km' => $maintenanceType === 'general'
                    ? ($truck->km_maintenance_interval ?? $defaultInterval)
                    : $defaultInterval,
                'warning_threshold_km' => config('maintenance.warning_threshold_km', 500),
                'status' => 'green',
                'next_maintenance_km' => $defaultInterval,
                'is_active' => true,
            ]
        );
    }

    private function ensureProfiles(Truck $truck): Collection
    {
        $types = array_keys((array) config('maintenance.types', []));

        if (empty($types)) {
            $types = ['general'];
        }

        foreach ($types as $type) {
            $this->ensureProfile($truck, $type);
        }

        return $truck->maintenanceProfiles()->where('is_active', true)->get();
    }
}
