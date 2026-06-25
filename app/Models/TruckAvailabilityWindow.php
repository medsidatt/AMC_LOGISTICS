<?php

namespace App\Models;

use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A period during which a truck is unavailable (or, exceptionally, a SYSTEM-derived
 * window). Real downtime — the source of truth for availability calculations.
 */
class TruckAvailabilityWindow extends Model
{
    public const TYPE_REST       = 'REST';
    public const TYPE_MAINTENANCE = 'MAINTENANCE';
    public const TYPE_INSPECTION = 'INSPECTION';
    public const TYPE_BREAKDOWN  = 'BREAKDOWN';
    public const TYPE_SHUTDOWN   = 'SHUTDOWN';

    public const TYPES = [
        self::TYPE_REST, self::TYPE_MAINTENANCE, self::TYPE_INSPECTION,
        self::TYPE_BREAKDOWN, self::TYPE_SHUTDOWN,
    ];

    public const SOURCE_MANUAL = 'MANUAL';
    public const SOURCE_SYSTEM = 'SYSTEM';

    protected $guarded = [];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    /** Windows overlapping the given [start, end] datetime range. */
    public function scopeOverlapping($query, $start, $end)
    {
        return $query->where('start_at', '<=', $end)->where('end_at', '>=', $start);
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
