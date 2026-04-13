<?php

namespace App\Models;

use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FuelEvent extends Model
{
    public const TYPE_REFILL = 'refill';
    public const TYPE_DROP = 'drop';
    public const TYPE_THEFT_SUSPECTED = 'theft_suspected';

    protected $table = 'fuel_events';

    protected $fillable = [
        'truck_id',
        'event_type',
        'litres_delta',
        'litres_before',
        'litres_after',
        'odometer_km',
        'latitude',
        'longitude',
        'ignition_on',
        'detected_at',
        'snapshot_before_id',
        'snapshot_after_id',
        'reviewed_at',
        'reviewed_by',
        'notes',
    ];

    protected $casts = [
        'detected_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'litres_delta' => 'float',
        'litres_before' => 'float',
        'litres_after' => 'float',
        'odometer_km' => 'float',
        'latitude' => 'float',
        'longitude' => 'float',
        'ignition_on' => 'boolean',
    ];

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function snapshotBefore(): BelongsTo
    {
        return $this->belongsTo(TruckTelemetrySnapshot::class, 'snapshot_before_id');
    }

    public function snapshotAfter(): BelongsTo
    {
        return $this->belongsTo(TruckTelemetrySnapshot::class, 'snapshot_after_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeUnreviewed(Builder $q): Builder
    {
        return $q->whereNull('reviewed_at');
    }

    public function scopeOfType(Builder $q, string $type): Builder
    {
        return $q->where('event_type', $type);
    }

    public function theftIncident(): HasOne
    {
        return $this->hasOne(TheftIncident::class);
    }
}
