<?php

namespace App\Models;

use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FleetiDailyRecord extends Model
{
    protected $guarded = [];

    protected $casts = [
        'record_date' => 'date',
        'kilometers' => 'float',
        'volume_initial' => 'float',
        'volume_final' => 'float',
        'consumed' => 'float',
        'consumed_per_100km' => 'float',
        'refills_volume' => 'float',
        'drains_volume' => 'float',
        'refills_count' => 'integer',
        'drains_count' => 'integer',
    ];

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
