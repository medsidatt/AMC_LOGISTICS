<?php

namespace App\Models;

use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TruckRestWindow extends Model
{
    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public const REASON_SCHEDULED_MAINTENANCE = 'scheduled_maintenance';
    public const REASON_OIL_CHANGE = 'oil_change';
    public const REASON_TIRE_CHANGE = 'tire_change';
    public const REASON_DRIVER_REST = 'driver_rest';
    public const REASON_SURPLUS_CAPACITY = 'surplus_capacity';
    public const REASON_ANOMALY_REVIEW = 'anomaly_review';

    public const REASON_LABELS = [
        self::REASON_SCHEDULED_MAINTENANCE => 'Maintenance programmée',
        self::REASON_OIL_CHANGE => 'Vidange',
        self::REASON_TIRE_CHANGE => 'Changement pneus',
        self::REASON_DRIVER_REST => 'Repos chauffeur',
        self::REASON_SURPLUS_CAPACITY => 'Capacité excédentaire',
        self::REASON_ANOMALY_REVIEW => 'Revue après anomalie',
    ];

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function maintenance(): BelongsTo
    {
        return $this->belongsTo(Maintenance::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function coversDate(\DateTimeInterface $date): bool
    {
        $d = \Carbon\Carbon::parse($date)->toDateString();
        return $d >= $this->start_date->toDateString() && $d <= $this->end_date->toDateString();
    }
}
