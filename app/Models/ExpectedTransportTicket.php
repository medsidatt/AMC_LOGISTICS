<?php

namespace App\Models;

use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpectedTransportTicket extends Model
{
    public const STATUS_EXPECTED  = 'expected';
    public const STATUS_MATCHED   = 'matched';
    public const STATUS_MISSING   = 'missing';
    public const STATUS_DISMISSED = 'dismissed';

    protected $table = 'expected_transport_tickets';

    protected $guarded = [];

    protected $casts = [
        'loaded_at' => 'datetime',
        'left_at' => 'datetime',
        'deadline_at' => 'datetime',
    ];

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(DailyDispatch::class, 'daily_dispatch_id');
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function transportTracking(): BelongsTo
    {
        return $this->belongsTo(TransportTracking::class);
    }

    public function dismisser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dismissed_by');
    }

    public function scopeStatus(Builder $q, string $status): Builder
    {
        return $q->where('status', $status);
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('status', [self::STATUS_EXPECTED, self::STATUS_MISSING]);
    }
}
