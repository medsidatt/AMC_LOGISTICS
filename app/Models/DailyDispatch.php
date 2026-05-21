<?php

namespace App\Models;

use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyDispatch extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_READ = 'read';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    protected $guarded = [];

    protected $casts = [
        'dispatch_date' => 'date',
        'notified_at' => 'datetime',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function wishProvider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'wish_provider_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeOnDate(Builder $q, string $date): Builder
    {
        return $q->whereDate('dispatch_date', $date);
    }

    public function markPending(): void
    {
        $this->forceFill([
            'notification_status' => self::STATUS_PENDING,
            'notification_error' => null,
            'whatsapp_message_id' => null,
            'notified_at' => null,
        ])->save();
    }

    public function markSent(string $wamid): void
    {
        $this->forceFill([
            'notification_status' => self::STATUS_SENT,
            'notification_error' => null,
            'whatsapp_message_id' => $wamid,
            'notified_at' => now(),
        ])->save();
    }

    public function markFailed(string $error): void
    {
        $this->forceFill([
            'notification_status' => self::STATUS_FAILED,
            'notification_error' => mb_substr($error, 0, 1000),
        ])->save();
    }

    public function markSkipped(string $reason): void
    {
        $this->forceFill([
            'notification_status' => self::STATUS_SKIPPED,
            'notification_error' => mb_substr($reason, 0, 1000),
        ])->save();
    }
}
