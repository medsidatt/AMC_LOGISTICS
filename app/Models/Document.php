<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasFactory, SoftDeletes;

    /** External-sync lifecycle (local-first → background provider sync). */
    public const SYNC_PENDING = 'pending';
    public const SYNC_SYNCING = 'syncing';
    public const SYNC_SYNCED  = 'synced';
    public const SYNC_FAILED  = 'failed';

    protected $guarded = [];

    protected $casts = [
        'synced_at' => 'datetime',
        'retry_count' => 'integer',
    ];

    public function transportTracking(): BelongsTo
    {
        return $this->belongsTo(TransportTracking::class);
    }

    public function inspectionChecklistIssue(): BelongsTo
    {
        return $this->belongsTo(InspectionChecklistIssue::class);
    }

    public function dailyChecklistIssue(): BelongsTo
    {
        return $this->belongsTo(DailyChecklistIssue::class);
    }
}
