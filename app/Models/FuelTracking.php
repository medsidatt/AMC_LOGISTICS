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
    ];

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }
}
