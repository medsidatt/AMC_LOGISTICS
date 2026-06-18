<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FleetObjectiveTruck extends Model
{
    protected $guarded = [];

    protected $casts = [
        'target_tons' => 'decimal:2',
        'capacity_tonnage' => 'decimal:2',
    ];

    public function objective(): BelongsTo
    {
        return $this->belongsTo(FleetObjective::class, 'fleet_objective_id');
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }
}
