<?php

namespace App\Models;

use App\Enums\Fuel\FuelSource;
use App\Enums\Fuel\TransactionType;
use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The canonical fuel financial ledger (R4 schema `fuel_card_transactions`) — every accepted card/
 * account movement (recharges AND account transfers, distinguished by transaction_type). Holds the
 * immutable financial facts, the validator's proposal snapshot, and the effective (review-mutable)
 * attribution/eligibility. `truck_id` is nullable so a non-fleet transaction is never lost.
 *
 * Models only: no import/decision logic here — ClassificationPolicy owns decisions (R7+).
 */
class FuelCardTransaction extends Model
{
    protected $table = 'fuel_card_transactions';

    protected $guarded = [];

    protected $casts = [
        'source' => FuelSource::class,
        'transaction_type' => TransactionType::class,
        'amount_fcfa' => 'float',
        'estimated_litres' => 'float',
        'price_per_litre' => 'float',
        'occurred_at' => 'datetime',
        'kpi_eligible' => 'boolean',
        'proposed_kpi_eligible' => 'boolean',
        'proposed_technical_findings' => 'array',
        'proposed_business_findings' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(FuelImportBatch::class, 'fuel_import_batch_id');
    }

    public function reviewEvents(): HasMany
    {
        return $this->hasMany(FuelTransactionReviewEvent::class);
    }
}
