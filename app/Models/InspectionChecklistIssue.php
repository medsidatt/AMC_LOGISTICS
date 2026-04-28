<?php

namespace App\Models;

use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InspectionChecklistIssue extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'flagged' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    public function inspectionChecklist(): BelongsTo
    {
        return $this->belongsTo(InspectionChecklist::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
