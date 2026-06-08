<?php

namespace App\Models;

use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DailyChecklistIssue extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'flagged' => 'boolean',
        'resolved_at' => 'datetime',
        'reported_at' => 'datetime',
        'parts_cost' => 'decimal:2',
        'labor_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'cost_recorded_at' => 'datetime',
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

    public function costRecorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cost_recorded_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function devis(): HasMany
    {
        return $this->documents()->where('type', 'devis');
    }
}
