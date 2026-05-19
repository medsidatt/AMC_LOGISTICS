<?php

namespace App\Models;

use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientDemandPlan extends Model
{
    protected $guarded = [];

    protected $casts = [
        'week_start_date' => 'date',
        'required_tons' => 'float',
        'required_trucks' => 'integer',
        'priority' => 'integer',
    ];

    public const PRODUCTS = ['0/3', '3/8', '8/16'];

    public const PRIORITY_LABELS = [
        1 => 'Critique',
        2 => 'Haute',
        3 => 'Normale',
        4 => 'Basse',
        5 => 'Opportuniste',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TruckAssignment::class);
    }

    public function getAllocatedTonsAttribute(): float
    {
        return (float) $this->assignments()->sum('planned_tonnage');
    }

    public function getCoverageRateAttribute(): float
    {
        if ((float) $this->required_tons <= 0.0) {
            return 0.0;
        }
        return round(min(1.0, $this->allocated_tons / $this->required_tons), 4);
    }
}
