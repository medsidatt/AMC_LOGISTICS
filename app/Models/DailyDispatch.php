<?php

namespace App\Models;

use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyDispatch extends Model
{
    // WhatsApp notification lifecycle
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_READ = 'read';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    // Live operational status (French operational vocabulary)
    public const STATUS_LIVE_FILE_CARRIERE     = 'FILE_CARRIERE';
    public const STATUS_LIVE_CHARGEMENT        = 'CHARGEMENT';
    public const STATUS_LIVE_EN_ROUTE          = 'EN_ROUTE';
    public const STATUS_LIVE_RETOUR            = 'RETOUR';
    public const STATUS_LIVE_CHEZ_CLIENT       = 'CHEZ_CLIENT';
    public const STATUS_LIVE_RAVITAILLEMENT    = 'RAVITAILLEMENT';
    public const STATUS_LIVE_PASSAGE_FRONTIERE = 'PASSAGE_FRONTIERE';
    public const STATUS_LIVE_A_LA_BASE         = 'A_LA_BASE';
    public const STATUS_LIVE_ARRET_LONG        = 'ARRET_LONG';
    public const STATUS_LIVE_ARRET             = 'ARRET';
    public const STATUS_LIVE_OFFLINE           = 'OFFLINE';
    public const STATUS_LIVE_TERMINE           = 'TERMINE';

    protected $guarded = [];

    protected $casts = [
        'dispatch_date' => 'date',
        'notified_at' => 'datetime',
        'current_status_at' => 'datetime',
        'eta_at' => 'datetime',
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

    public function currentPlace(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'current_place_id');
    }

    public function lastEvent(): BelongsTo
    {
        return $this->belongsTo(DailyDispatchEvent::class, 'last_event_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(DailyDispatchEvent::class, 'daily_dispatch_id');
    }

    public function expectedTickets(): HasMany
    {
        return $this->hasMany(ExpectedTransportTicket::class, 'daily_dispatch_id');
    }

    public function scopeOnDate(Builder $q, string $date): Builder
    {
        return $q->whereDate('dispatch_date', $date);
    }

    public function scopeNotified(Builder $q): Builder
    {
        return $q->whereIn('notification_status', [
            self::STATUS_SENT,
            self::STATUS_DELIVERED,
            self::STATUS_READ,
        ]);
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

    /**
     * True when the dispatch's live timeline has reached arrived_base
     * (closes the round trip).
     */
    public function isCompleted(): bool
    {
        return $this->current_status === self::STATUS_LIVE_TERMINE;
    }
}
