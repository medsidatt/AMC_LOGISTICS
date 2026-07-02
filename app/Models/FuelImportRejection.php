<?php

namespace App\Models;

use App\Enums\Fuel\FuelSource;
use App\Enums\Fuel\TransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A row REJECTED by the import (technical-fatal: malformed / impossible date-amount / duplicate) —
 * kept for audit (R4 schema `fuel_import_rejections`) with the original CSV + detected context.
 * Only rejected rows live here; accepted movements are in {@see FuelCardTransaction}.
 */
class FuelImportRejection extends Model
{
    protected $table = 'fuel_import_rejections';

    protected $guarded = [];

    protected $casts = [
        'source' => FuelSource::class,
        'transaction_type' => TransactionType::class,
        'technical_findings' => 'array',
        'amount_fcfa' => 'float',
        'estimated_litres' => 'float',
        'occurred_at' => 'datetime',
        'needs_review' => 'boolean',
        'line_number' => 'integer',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(FuelImportBatch::class, 'fuel_import_batch_id');
    }

    public function detectedTruck(): BelongsTo
    {
        return $this->belongsTo(Truck::class, 'detected_truck_id');
    }

    public function detectedDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'detected_driver_id');
    }
}
