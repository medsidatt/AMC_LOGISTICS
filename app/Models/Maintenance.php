<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Maintenance extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'maintenance_date' => 'date',
    ];

    public const TYPE_GENERAL = 'general';
    public const TYPE_OIL = 'oil';
    public const TYPE_TIRES = 'tires';
    public const TYPE_FILTERS = 'filters';

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }
}
