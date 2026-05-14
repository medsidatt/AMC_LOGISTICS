<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Maintenance extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'maintenance_date' => 'date',
        'filter_oil_changed' => 'boolean',
        'filter_hydraulic_changed' => 'boolean',
        'filter_air_changed' => 'boolean',
        'filter_fuel_changed' => 'boolean',
        'oil_change_km' => 'decimal:2',
        'next_oil_change_km' => 'decimal:2',
        'kilometers_at_maintenance' => 'decimal:2',
        'trigger_km' => 'decimal:2',
    ];

    public const TYPE_GENERAL = 'general';
    public const TYPE_OIL = 'oil';
    public const TYPE_TIRES = 'tires';
    public const TYPE_FILTERS = 'filters';

    public const OIL_TYPES = [
        'shell_rimula_r4_15w40' => 'Shell Rimula R4 X 15W 40',
        'shell_rimula_r3_15w40' => 'Shell Rimula R3 X 15W 40',
        'shell_rimula_r2_extra_15w40' => 'Shell Rimula R2 extra 15W 40',
        'shell_rimula_r2_50' => 'Shell Rimula R2-50',
        'shell_rimula_r1_50' => 'Shell Rimula R1-50',
        'other' => 'Autre',
    ];

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(TruckMaintenanceProfile::class, 'truck_maintenance_profile_id');
    }

    public function inspectionIssues(): HasMany
    {
        return $this->hasMany(InspectionChecklistIssue::class);
    }
}
