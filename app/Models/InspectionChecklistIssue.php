<?php

namespace App\Models;

use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InspectionChecklistIssue extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'flagged' => 'boolean',
        'resolved_at' => 'datetime',
        'parts_cost' => 'decimal:2',
        'labor_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'cost_recorded_at' => 'datetime',
    ];

    public function inspectionChecklist(): BelongsTo
    {
        return $this->belongsTo(InspectionChecklist::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function maintenance(): BelongsTo
    {
        return $this->belongsTo(Maintenance::class);
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
