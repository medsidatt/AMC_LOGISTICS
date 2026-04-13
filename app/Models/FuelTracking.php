<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelTracking extends Model
{
    protected $guarded = [];

    protected $casts = [
        'litres' => 'float',
        'kilometers_at' => 'float',
        'engine_hours_at' => 'float',
        'latitude' => 'float',
        'longitude' => 'float',
        'ignition_on' => 'boolean',
    ];

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(TruckTelemetrySnapshot::class, 'telemetry_snapshot_id');
    }
}
