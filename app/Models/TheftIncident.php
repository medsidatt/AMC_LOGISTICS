<?php

namespace App\Models;

use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TheftIncident extends Model
{
    use SoftDeletes;

    public const TYPE_FUEL_SIPHONING = 'fuel_siphoning';
    public const TYPE_WEIGHT_GAP = 'weight_gap';
    public const TYPE_UNAUTHORIZED_STOP = 'unauthorized_stop';
    public const TYPE_ROUTE_DEVIATION = 'route_deviation';
    public const TYPE_OFF_HOURS_MOVEMENT = 'off_hours_movement';

    public const SEVERITY_LOW = 'low';
    public const SEVERITY_MEDIUM = 'medium';
    public const SEVERITY_HIGH = 'high';

    public const STATUS_PENDING = 'pending';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_DISMISSED = 'dismissed';
    public const STATUS_CONFIRMED = 'confirmed';

    protected $table = 'theft_incidents';

    protected $guarded = [];

    protected $casts = [
        'detected_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'evidence' => 'array',
    ];

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function transportTracking(): BelongsTo
    {
        return $this->belongsTo(TransportTracking::class);
    }

    public function tripSegment(): BelongsTo
    {
        return $this->belongsTo(TripSegment::class);
    }

    public function truckStop(): BelongsTo
    {
        return $this->belongsTo(TruckStop::class);
    }

    public function fuelEvent(): BelongsTo
    {
        return $this->belongsTo(FuelEvent::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PENDING);
    }

    public function scopeOfType(Builder $q, string $type): Builder
    {
        return $q->where('type', $type);
    }

    public function scopeOfSeverity(Builder $q, string $severity): Builder
    {
        return $q->where('severity', $severity);
    }

    public function scopeForTruck(Builder $q, int $truckId): Builder
    {
        return $q->where('truck_id', $truckId);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function dedupKey(): ?string
    {
        return data_get($this->evidence, 'dedup_key');
    }
}
