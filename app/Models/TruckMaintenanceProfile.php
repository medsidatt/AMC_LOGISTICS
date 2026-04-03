<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TruckMaintenanceProfile extends Model
{
    protected $guarded = [];

    protected $casts = [
        'interval_km' => 'float',
        'warning_threshold_km' => 'float',
        'last_maintenance_km' => 'float',
        'next_maintenance_km' => 'float',
        'last_calculated_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }
}
