<?php

namespace App\Models;

use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One import run — the audit anchor linking accepted transactions and rejected rows to the
 * file/user/time they came from, with counts by source/type/finding/decision (R4 schema).
 */
class FuelImportBatch extends Model
{
    protected $guarded = [];

    protected $casts = [
        'category_counts' => 'array',
        'source_counts' => 'array',
        'type_counts' => 'array',
        'technical_finding_counts' => 'array',
        'business_finding_counts' => 'array',
        'decision_counts' => 'array',
        'total_rows' => 'integer',
        'accepted_rows' => 'integer',
        'rejected_rows' => 'integer',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(FuelCardTransaction::class);
    }

    public function rejections(): HasMany
    {
        return $this->hasMany(FuelImportRejection::class);
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
