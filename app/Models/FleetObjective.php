<?php

namespace App\Models;

use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FleetObjective extends Model
{
    public const PERIOD_WEEK   = 'WEEK';
    public const PERIOD_MONTH  = 'MONTH';
    public const PERIOD_YEAR   = 'YEAR';
    public const PERIOD_CUSTOM = 'CUSTOM';

    /** Specificity rank — lower = more specific. Drives hierarchical resolution. */
    public const PERIOD_RANK = [
        self::PERIOD_CUSTOM => 0,
        self::PERIOD_WEEK   => 1,
        self::PERIOD_MONTH  => 2,
        self::PERIOD_YEAR   => 3,
    ];

    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'target_tons' => 'decimal:2',
        'archived_at' => 'datetime',
    ];

    /** Active (non-archived) objectives — the only ones that drive resolution. */
    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }

    /** Objectives whose [start_date,end_date] fully contains the given period. */
    public function scopeContaining($query, string $start, string $end)
    {
        return $query->whereDate('start_date', '<=', $start)
            ->whereDate('end_date', '>=', $end);
    }

    /** Objectives whose [start_date,end_date] overlaps the given period at all. */
    public function scopeOverlapping($query, string $start, string $end)
    {
        return $query->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Per-truck target snapshot for this objective period. */
    public function truckTargets(): HasMany
    {
        return $this->hasMany(FleetObjectiveTruck::class);
    }
}
