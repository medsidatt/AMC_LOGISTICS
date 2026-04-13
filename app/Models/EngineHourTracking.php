<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EngineHourTracking extends Model
{
    protected $table = 'engine_hour_trackings';

    protected $fillable = [
        'truck_id',
        'telemetry_snapshot_id',
        'hours_delta',
        'date',
        'source',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'hours_delta' => 'float',
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
