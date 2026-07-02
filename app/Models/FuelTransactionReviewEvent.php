<?php

namespace App\Models;

use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit of a reviewer's decision on a {@see FuelCardTransaction} (R4 schema
 * `fuel_transaction_review_events`): before/after snapshot of the effective attribution + eligibility.
 * Only `created_at` is managed (no `updated_at`) — events are never mutated.
 */
class FuelTransactionReviewEvent extends Model
{
    protected $table = 'fuel_transaction_review_events';

    /** Append-only: the table has no updated_at column. */
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'created_at' => 'datetime',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FuelCardTransaction::class, 'fuel_card_transaction_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
