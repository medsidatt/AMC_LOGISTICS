<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TruckStop extends Model
{
    public const CLASS_KNOWN_BASE = 'known_base';
    public const CLASS_KNOWN_PROVIDER = 'known_provider';
    public const CLASS_KNOWN_CLIENT = 'known_client';
    public const CLASS_KNOWN_FUEL_STATION = 'known_fuel_station';
    public const CLASS_KNOWN_PARKING = 'known_parking';
    public const CLASS_UNKNOWN = 'unknown';
    public const CLASS_ROADSIDE = 'roadside';

    protected $table = 'truck_stops';

    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_seconds' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'ignition_was_off' => 'boolean',
        'fuel_litres_at_start' => 'float',
        'fuel_litres_at_end' => 'float',
    ];

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function startSnapshot(): BelongsTo
    {
        return $this->belongsTo(TruckTelemetrySnapshot::class, 'start_snapshot_id');
    }

    public function endSnapshot(): BelongsTo
    {
        return $this->belongsTo(TruckTelemetrySnapshot::class, 'end_snapshot_id');
    }

    public function theftIncidents(): HasMany
    {
        return $this->hasMany(TheftIncident::class);
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereNull('ended_at');
    }

    public function scopeClosed(Builder $q): Builder
    {
        return $q->whereNotNull('ended_at');
    }

    public function scopeUnknownLocation(Builder $q): Builder
    {
        return $q->where('classification', self::CLASS_UNKNOWN);
    }

    public function scopeForTruck(Builder $q, int $truckId): Builder
    {
        return $q->where('truck_id', $truckId);
    }

    public function isOpen(): bool
    {
        return $this->ended_at === null;
    }

    public function fuelDeltaLitres(): ?float
    {
        if ($this->fuel_litres_at_start === null || $this->fuel_litres_at_end === null) {
            return null;
        }

        return round((float) $this->fuel_litres_at_end - (float) $this->fuel_litres_at_start, 2);
    }
}
