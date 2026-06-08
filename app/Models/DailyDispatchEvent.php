<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyDispatchEvent extends Model
{
    public const TYPE_QUEUED_AT_QUARRY    = 'queued_at_quarry';
    public const TYPE_LOADING_STARTED     = 'loading_started';
    public const TYPE_LOADED_AND_LEFT     = 'loaded_and_left';
    public const TYPE_REFUEL              = 'refuel';
    public const TYPE_FUEL_LOSS           = 'fuel_loss';
    public const TYPE_LONG_STOP           = 'long_stop';
    public const TYPE_OFF_ROUTE           = 'off_route';
    public const TYPE_BORDER_CROSSED      = 'border_crossed';
    public const TYPE_ARRIVED_CLIENT      = 'arrived_client';
    public const TYPE_UNLOADED            = 'unloaded';
    public const TYPE_RETURNING           = 'returning';
    public const TYPE_ARRIVED_BASE        = 'arrived_base';
    public const TYPE_OFFLINE             = 'offline';
    public const TYPE_ONLINE              = 'online';
    public const TYPE_BREAKDOWN_SUSPECTED = 'breakdown_suspected';

    public const SOURCE_GPS    = 'gps';
    public const SOURCE_TICKET = 'ticket';
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_SYSTEM = 'system';

    protected $table = 'daily_dispatch_events';

    protected $guarded = [];

    protected $casts = [
        'occurred_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'payload' => 'array',
    ];

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(DailyDispatch::class, 'daily_dispatch_id');
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(TruckTelemetrySnapshot::class, 'snapshot_id');
    }

    public function scopeForDispatch(Builder $q, int $dispatchId): Builder
    {
        return $q->where('daily_dispatch_id', $dispatchId);
    }

    public function scopeOfType(Builder $q, string $type): Builder
    {
        return $q->where('type', $type);
    }

    public function scopeRecent(Builder $q, int $hours = 6): Builder
    {
        return $q->where('occurred_at', '>=', now()->subHours($hours));
    }

    /**
     * Stable hash key used to make repeated derivation idempotent.
     */
    public static function buildDedupeKey(int $dispatchId, string $type, string $bucket): string
    {
        return substr(sha1("{$dispatchId}|{$type}|{$bucket}"), 0, 64);
    }
}
