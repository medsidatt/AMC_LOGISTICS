<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DailyChecklistIssue extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'flagged' => 'boolean',
        'resolved_at' => 'datetime',
        'reported_at' => 'datetime',
    ];

    const SEVERITY_OPTIONS = [
        'minor' => 'Mineur',
        'major' => 'Majeur',
        'critical' => 'Critique',
    ];

    public function dailyChecklist(): BelongsTo
    {
        return $this->belongsTo(DailyChecklist::class);
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
