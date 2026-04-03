<?php

namespace App\Models;

use App\Http\Traits\TracksActions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class Truck extends Model
{
    use SoftDeletes, TracksActions;

    protected $guarded = [];

    protected $casts = [
        'fleeti_last_synced_at' => 'datetime',
        'km_maintenance_interval' => 'float',
    ];

    // Maximum rotations before maintenance is required
    public const MAX_ROTATIONS_BEFORE_MAINTENANCE = 12;
    // Maximum kilometers before maintenance is required
    public const MAX_KM_BEFORE_MAINTENANCE = 10000;

    public function transporter(): BelongsTo
    {
        return $this->belongsTo(Transporter::class);
    }

    public function transportTrackings(): HasMany
    {
        return $this->hasMany(TransportTracking::class);
    }

    public function kilometerTrackings(): HasMany
    {
        return $this->hasMany(KilometerTracking::class);
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }

    public function maintenanceProfiles(): HasMany
    {
        return $this->hasMany(TruckMaintenanceProfile::class);
    }

    /**
     * Get the last maintenance record for this truck
     */
    public function lastMaintenance(?string $maintenanceType = null): ?Maintenance
    {
        $query = $this->maintenances()->latest('maintenance_date');

        if (
            $maintenanceType !== null
            && Schema::hasColumn('maintenances', 'maintenance_type')
        ) {
            $query->where('maintenance_type', $maintenanceType);
        }

        return $query->first();
    }

    /**
     * Get the date of the last maintenance
     */
    public function getLastMaintenanceDateAttribute(): ?string
    {
        return $this->lastMaintenance()?->maintenance_date;
    }

    // ------------------------------------------------------------------------
    // Rotation Based Maintenance Logic
    // ------------------------------------------------------------------------

    /**
     * Count rotations (transport trackings) since the last maintenance
     * If no maintenance exists, count all rotations for this truck
     */
    public function getRotationsSinceMaintenanceAttribute(): int
    {
        // Rotations are driven only by tires/wheels maintenance records.
        $lastMaintenance = $this->lastMaintenance(Maintenance::TYPE_TIRES) ?? $this->lastMaintenance();

        $query = $this->transportTrackings();

        if ($lastMaintenance) {
            // Count transport trackings after the last maintenance date
            $query->where(function ($q) use ($lastMaintenance) {
                $q->whereDate('client_date', '>', $lastMaintenance->maintenance_date)
                  ->orWhereDate('provider_date', '>', $lastMaintenance->maintenance_date);
            });
        }

        return $query->count();
    }

    /**
     * Check if maintenance is due based on rotations
     */
    public function getMaintenanceDueAttribute(): bool
    {
        return $this->rotations_since_maintenance >= self::MAX_ROTATIONS_BEFORE_MAINTENANCE;
    }

    /**
     * Get remaining rotations before maintenance is required
     */
    public function remainingRotations(): int
    {
        return max(0, self::MAX_ROTATIONS_BEFORE_MAINTENANCE - $this->rotations_since_maintenance);
    }

    /**
     * Get maintenance level indicator based on rotations (red, yellow, green)
     */
    public function maintenanceLevel(): string
    {
        $remaining = $this->remainingRotations();

        if ($remaining === 0) {
            return 'red';
        } elseif ($remaining <= 6) {
            return 'yellow';
        }
        return 'green';
    }

    // ------------------------------------------------------------------------
    // Kilometer Based Maintenance Logic
    // ------------------------------------------------------------------------

    /**
     * Sum kilometers since the last maintenance
     */
    public function getKmSinceMaintenanceAttribute(): float
    {
        // Kilometers are driven only by oil/engine maintenance records.
        $lastMaintenance = $this->lastMaintenance(Maintenance::TYPE_OIL) ?? $this->lastMaintenance();
        $currentTotal = (float) ($this->total_kilometers ?? 0);

        if ($lastMaintenance && ! is_null($lastMaintenance->kilometers_at_maintenance)) {
            return max(0.0, round($currentTotal - (float) $lastMaintenance->kilometers_at_maintenance, 2));
        }

        $query = $this->kilometerTrackings();
        if ($lastMaintenance) {
            $query->whereDate('date', '>', $lastMaintenance->maintenance_date);
        }

        $trackedDistance = (float) $query->sum('kilometers');
        if ($trackedDistance > 0) {
            return $trackedDistance;
        }

        return $lastMaintenance ? 0.0 : $currentTotal;
    }

    /**
     * Check if maintenance is due based on kilometers
     */
    public function getKmMaintenanceDueAttribute(): bool
    {
        return $this->total_kilometers >= $this->nextMaintenanceAtKm();
    }

    /**
     * Get remaining kilometers before maintenance is required
     */
    public function remainingKm(): float
    {
        return max(0, round($this->nextMaintenanceAtKm() - (float) $this->total_kilometers, 2));
    }

    /**
     * Get maintenance level indicator based on kilometers (red, yellow, green)
     */
    public function kmMaintenanceLevel(): string
    {
        $remaining = $this->remainingKm();
        $interval = $this->kmMaintenanceInterval();

        if ($remaining === 0) {
            return 'red';
        } elseif ($remaining <= ($interval / 2)) {
            return 'yellow';
        }
        return 'green';
    }

    public function usesKilometerMaintenance(): bool
    {
        return $this->maintenance_type === 'kilometers';
    }

    public function isMaintenanceDueByType(): bool
    {
        return $this->usesKilometerMaintenance()
            ? $this->km_maintenance_due
            : $this->maintenance_due;
    }

    public function maintenanceLevelByType(): string
    {
        return $this->usesKilometerMaintenance()
            ? $this->kmMaintenanceLevel()
            : $this->maintenanceLevel();
    }

    public function maintenanceRemainingByType(): float|int
    {
        return $this->usesKilometerMaintenance()
            ? $this->remainingKm()
            : $this->remainingRotations();
    }

    public function maintenanceUnitByType(): string
    {
        return $this->usesKilometerMaintenance() ? 'km' : 'rotations';
    }

    public function maintenanceCounterByType(): float|int
    {
        return $this->usesKilometerMaintenance()
            ? $this->km_since_maintenance
            : $this->rotations_since_maintenance;
    }

    public function kmMaintenanceInterval(): float
    {
        if (! Schema::hasTable('truck_maintenance_profiles')) {
            $interval = (float) ($this->km_maintenance_interval ?? 0);
            return $interval > 0 ? $interval : self::MAX_KM_BEFORE_MAINTENANCE;
        }

        $profile = $this->maintenanceProfiles()->where('maintenance_type', 'general')->first();
        if ($profile) {
            return max(1.0, (float) $profile->interval_km);
        }

        $interval = (float) ($this->km_maintenance_interval ?? 0);
        return $interval > 0 ? $interval : self::MAX_KM_BEFORE_MAINTENANCE;
    }

    public function lastMaintenanceKm(): float
    {
        $lastMaintenance = $this->lastMaintenance(Maintenance::TYPE_OIL) ?? $this->lastMaintenance();
        if (! $lastMaintenance) {
            return 0.0;
        }

        if (! is_null($lastMaintenance->kilometers_at_maintenance)) {
            return (float) $lastMaintenance->kilometers_at_maintenance;
        }

        return max(0.0, (float) $this->total_kilometers - (float) $this->km_since_maintenance);
    }

    public function nextMaintenanceAtKm(): float
    {
        if (! Schema::hasTable('truck_maintenance_profiles')) {
            return round($this->lastMaintenanceKm() + $this->kmMaintenanceInterval(), 2);
        }

        $profile = $this->maintenanceProfiles()->where('maintenance_type', 'general')->first();
        if ($profile) {
            return round((float) $profile->next_maintenance_km, 2);
        }

        return round($this->lastMaintenanceKm() + $this->kmMaintenanceInterval(), 2);
    }

    // ------------------------------------------------------------------------
    // General Maintenance Actions
    // ------------------------------------------------------------------------

    /**
     * Check if maintenance already exists for a given date
     */
    public function hasMaintenanceOnDate(string $date, string $maintenanceType = 'general'): bool
    {
        $query = $this->maintenances()->whereDate('maintenance_date', $date);
        if (Schema::hasColumn('maintenances', 'maintenance_type')) {
            $query->where('maintenance_type', $maintenanceType);
        }

        return $query->exists();
    }

    /**
     * Mark maintenance as done - creates a maintenance record
     * Only one maintenance per day is allowed
     * This resets the effective counter for both systems since they both look at the last maintenance date
     */
    public function markMaintenanceDone(
        string $date = null,
        string $notes = null,
        string $maintenanceType = 'general',
        ?float $kilometersAtMaintenance = null
    ): Maintenance|false
    {
        $maintenanceDate = $date ?? now()->toDateString();

        // Check if maintenance already exists for this date
        if ($this->hasMaintenanceOnDate($maintenanceDate, $maintenanceType)) {
            return false;
        }

        if (Schema::hasTable('truck_maintenance_profiles')) {
            return app(\App\Services\MaintenanceStatusService::class)
                ->recordMaintenance($this, $maintenanceDate, $notes, $maintenanceType, $kilometersAtMaintenance);
        }

        $payload = [
            'maintenance_date' => $maintenanceDate,
            'notes' => $notes,
            'kilometers_at_maintenance' => $kilometersAtMaintenance ?? $this->total_kilometers,
        ];
        if (Schema::hasColumn('maintenances', 'maintenance_type')) {
            $payload['maintenance_type'] = $maintenanceType;
        }

        return $this->maintenances()->create($payload);
    }

    /**
     * Get detailed maintenance info (includes both systems)
     */
    public function getMaintenanceInfo(): array
    {
        $lastMaintenance = $this->lastMaintenance();
        $profiles = Schema::hasTable('truck_maintenance_profiles')
            ? app(\App\Services\MaintenanceStatusService::class)->recalculateForTruck($this)
            : collect();

        $lastMaintenanceKm = $this->lastMaintenanceKm();
        $kmInterval = $this->kmMaintenanceInterval();
        $nextMaintenanceAtKm = $this->nextMaintenanceAtKm();

        return [
            'last_maintenance_date' => $lastMaintenance?->maintenance_date,
            'rotations' => [
                'since_maintenance' => $this->rotations_since_maintenance,
                'remaining' => $this->remainingRotations(),
                'due' => $this->maintenance_due,
                'level' => $this->maintenanceLevel(),
            ],
            'kilometers' => [
                'current_total' => $this->total_kilometers,
                'interval' => $kmInterval,
                'last_maintenance_km' => $lastMaintenanceKm,
                'next_maintenance_at' => $nextMaintenanceAtKm,
                'since_maintenance' => $this->km_since_maintenance,
                'remaining' => $this->remainingKm(),
                'due' => $this->km_maintenance_due,
                'level' => $this->kmMaintenanceLevel(),
            ],
            'maintenance_types' => $profiles->map(fn ($profile) => [
                'type' => $profile->maintenance_type,
                'interval_km' => (float) $profile->interval_km,
                'next_maintenance_km' => (float) $profile->next_maintenance_km,
                'status' => $profile->status,
            ])->values()->all(),
        ];
    }
}
