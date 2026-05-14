<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FleetSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'monthly_target_tonnage' => 'float',
        'weight_gap_threshold' => 'float',
        'price_per_litre' => 'float',
        'discipline_weights' => 'array',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate(
            ['id' => 1],
            [
                'monthly_target_tonnage' => 0,
                'weight_gap_threshold' => 0.5,
                'price_per_litre' => 730,
            ],
        );
    }
}
