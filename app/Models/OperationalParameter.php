<?php

namespace App\Models;

use App\Services\OperationalParameterService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * R1.1 — a single configurable operational value (threshold, capacity, SLA, …).
 * Storage only; resolution and typing live in {@see OperationalParameterService}.
 */
class OperationalParameter extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'unit',
        'category',
        'description',
        'is_active',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Any write invalidates the resolver cache so reads never go stale.
        static::saved(fn () => Cache::forget(OperationalParameterService::CACHE_KEY));
        static::deleted(fn () => Cache::forget(OperationalParameterService::CACHE_KEY));
    }
}
