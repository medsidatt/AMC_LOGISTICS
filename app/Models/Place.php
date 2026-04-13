<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Place extends Model
{
    public const TYPE_BASE = 'base';
    public const TYPE_PROVIDER_SITE = 'provider_site';
    public const TYPE_CLIENT_SITE = 'client_site';
    public const TYPE_FUEL_STATION = 'fuel_station';
    public const TYPE_PARKING = 'parking';
    public const TYPE_UNKNOWN = 'unknown';

    protected $table = 'places';

    protected $guarded = [];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'radius_m' => 'integer',
        'is_auto_detected' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function truckStops(): HasMany
    {
        return $this->hasMany(TruckStop::class);
    }

    public function originTripSegments(): HasMany
    {
        return $this->hasMany(TripSegment::class, 'origin_place_id');
    }

    public function destinationTripSegments(): HasMany
    {
        return $this->hasMany(TripSegment::class, 'destination_place_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeOfType(Builder $q, string $type): Builder
    {
        return $q->where('type', $type);
    }

    /**
     * Map a TruckStop classification string based on the place's type.
     */
    public function classificationForStop(): string
    {
        return match ($this->type) {
            self::TYPE_BASE => 'known_base',
            self::TYPE_PROVIDER_SITE => 'known_provider',
            self::TYPE_CLIENT_SITE => 'known_client',
            self::TYPE_FUEL_STATION => 'known_fuel_station',
            self::TYPE_PARKING => 'known_parking',
            default => 'unknown',
        };
    }
}
