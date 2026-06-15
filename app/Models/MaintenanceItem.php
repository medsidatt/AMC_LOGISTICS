<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public const CATEGORIES = [
        'piece' => 'Pièce de rechange',
        'huile' => 'Huile / Lubrifiant',
        'autre' => "Autre / Main d'œuvre",
    ];

    public const UNITS = [
        'piece' => 'Pièce',
        'litre' => 'Litre',
        'unite' => 'Unité',
        'kg' => 'Kg',
        'jeu' => 'Jeu',
        'forfait' => 'Forfait',
    ];

    public function maintenance(): BelongsTo
    {
        return $this->belongsTo(Maintenance::class);
    }
}
