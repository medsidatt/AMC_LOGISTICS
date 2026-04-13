<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TripSegment extends Model
{
    protected $table = 'trip_segments';

    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'start_odometer_km' => 'float',
        'end_odometer_km' => 'float',
        'distance_km' => 'float',
        'fuel_consumed_litres' => 'float',
        'stop_count' => 'integer',
        'unknown_stop_count' => 'integer',
    ];

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function transportTracking(): BelongsTo
    {
        return $this->belongsTo(TransportTracking::class);
    }

    public function startSnapshot(): BelongsTo
    {
        return $this->belongsTo(TruckTelemetrySnapshot::class, 'start_snapshot_id');
    }

    public function endSnapshot(): BelongsTo
    {
        return $this->belongsTo(TruckTelemetrySnapshot::class, 'end_snapshot_id');
    }

    public function originPlace(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'origin_place_id');
    }

    public function destinationPlace(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'destination_place_id');
    }

    public function theftIncidents(): HasMany
    {
        return $this->hasMany(TheftIncident::class);
    }

    public function scopeLinked(Builder $q): Builder
    {
        return $q->whereNotNull('transport_tracking_id');
    }

    public function scopeUnlinked(Builder $q): Builder
    {
        return $q->whereNull('transport_tracking_id');
    }

    public function scopeForTruck(Builder $q, int $truckId): Builder
    {
        return $q->where('truck_id', $truckId);
    }

    /**
     * Return the truck_stops whose started_at falls inside this segment window.
     * Kept as a method (not an Eloquent relation) because truck_stops do not
     * have a direct FK to trip_segments — the link is derived by time window.
     */
    public function stopsInWindow()
    {
        return TruckStop::query()
            ->where('truck_id', $this->truck_id)
            ->where('started_at', '>=', $this->started_at)
            ->where('started_at', '<=', $this->ended_at);
    }
}
