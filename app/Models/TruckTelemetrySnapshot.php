<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class TruckTelemetrySnapshot extends Model
{
    public const UPDATED_AT = null; // snapshot table only has created_at in migration; avoid missing column errors

    protected $table = 'truck_telemetry_snapshots';

    protected $fillable = [
        'truck_id',
        'recorded_at',
        'synced_at',
        'source',
        'odometer_km',
        'engine_hours',
        'fuel_litres',
        'speed_kmh',
        'latitude',
        'longitude',
        'heading_deg',
        'gps_accuracy_m',
        'ignition_on',
        'movement_status',
        'battery_voltage',
        'signal_strength',
        'device_last_seen_at',
        'raw_payload',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'synced_at' => 'datetime',
        'device_last_seen_at' => 'datetime',
        'odometer_km' => 'float',
        'engine_hours' => 'float',
        'fuel_litres' => 'float',
        'speed_kmh' => 'float',
        'latitude' => 'float',
        'longitude' => 'float',
        'heading_deg' => 'float',
        'gps_accuracy_m' => 'float',
        'ignition_on' => 'boolean',
        'battery_voltage' => 'float',
        'signal_strength' => 'integer',
        'raw_payload' => 'array',
    ];

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function kilometerTracking(): HasOne
    {
        return $this->hasOne(KilometerTracking::class, 'telemetry_snapshot_id');
    }

    public function fuelTracking(): HasOne
    {
        return $this->hasOne(FuelTracking::class, 'telemetry_snapshot_id');
    }

    public function engineHourTracking(): HasOne
    {
        return $this->hasOne(EngineHourTracking::class, 'telemetry_snapshot_id');
    }

    public function scopeForTruck(Builder $q, int $truckId): Builder
    {
        return $q->where('truck_id', $truckId);
    }

    public function scopeBetween(Builder $q, Carbon $from, Carbon $to): Builder
    {
        return $q->whereBetween('recorded_at', [$from, $to]);
    }
}
