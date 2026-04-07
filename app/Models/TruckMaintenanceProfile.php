<?php

namespace App\Models;

use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TruckMaintenanceProfile extends Model
{
    protected $guarded = [];

    protected $casts = [
        'interval_km' => 'float',
        'warning_threshold_km' => 'float',
        'last_maintenance_km' => 'float',
        'next_maintenance_km' => 'float',
        'last_calculated_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $profile) {
            if ($profile->isDirty('interval_km')) {
                throw new \DomainException(
                    'interval_km is immutable. Deactivate this rule and create a new one.'
                );
            }
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class, 'truck_maintenance_profile_id');
    }
}
