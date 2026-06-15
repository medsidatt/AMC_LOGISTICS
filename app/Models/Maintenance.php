<?php

namespace App\Models;

use App\Models\Auth\User;
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
        'oil_quantity_liters' => 'decimal:2',
        'kilometers_at_maintenance' => 'decimal:2',
        'trigger_km' => 'decimal:2',
        'assigned_at' => 'datetime',
        'approved_at' => 'datetime',
        'control_checks' => 'array',
    ];

    /**
     * Post-work control checklist ("Fiche de contrôle après travaux").
     * Each item is recorded as 'bon' | 'mauvais' (or unset).
     */
    public const CONTROL_CHECKS = [
        'oil_leak' => "Vérification fuite d'huile (visible)",
        'water_leak' => "Vérification fuite d'eau (visible)",
        'brake_pad_wear' => 'Vérification usure plaquette de frein',
        'lights_signaling' => 'Vérification Éclairages et signalisation',
        'fluid_levels' => 'Vérification niveau (Liquide de frein et Liquide de Refroidissement)',
        'tire_wear_pressure' => 'Vérification usure pneumatique et pression',
        'battery_terminals' => 'Vérification État batterie et cosses',
        'belts' => 'Vérification Courroies Alternateur, Compresseur et Pompe',
        'ac' => 'Vérification fonctionnement Climatiseur',
        'cardan_bellows' => 'Vérification soufflets de cardan',
        'steering_bellows' => 'Vérification soufflets de crémaillère et rotule',
        'startup_noise' => "Vérification Bruit au démarrage et à l'extinction",
    ];

    public const TYPE_GENERAL = 'general';
    public const TYPE_OIL = 'oil';
    public const TYPE_TIRES = 'tires';
    public const TYPE_FILTERS = 'filters';

    public const STATUS_PENDING = 'pending';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_APPROVED = 'approved';

    public const OIL_TYPES = [
        'shell_rimula_r4_15w40' => 'Shell Rimula R4 X 15W 40',
        'shell_rimula_r3_15w40' => 'Shell Rimula R3 X 15W 40',
        'shell_rimula_r2_extra_15w40' => 'Shell Rimula R2 extra 15W 40',
        'shell_rimula_r2_50' => 'Shell Rimula R2-50',
        'shell_rimula_r1_50' => 'Shell Rimula R1-50',
        'other' => 'Autre',
    ];

    public const OIL_INTERVAL_KM = [
        'shell_rimula_r4_15w40' => 20000,
        'shell_rimula_r3_15w40' => 15000,
        'shell_rimula_r2_extra_15w40' => 12000,
        'shell_rimula_r2_50' => 10000,
        'shell_rimula_r1_50' => 8000,
        'other' => 10000,
    ];

    public const COMPONENT_STATUSES = [
        'NORMAL' => 'Normal',
        'À VÉRIFIER' => 'À vérifier',
        'À CHANGER' => 'À changer',
        'NETTOYÉ' => 'Nettoyé',
        'GRAISSÉ' => 'Graissé',
        'COMPLÉTÉ' => 'Complété',
        'REMPLACÉ' => 'Remplacé',
    ];

    /**
     * Once a maintenance is signed (status = approved) the record is sealed:
     * no further updates or deletes are allowed via Eloquent. The transition
     * INTO approved (signing) is permitted because the original status at
     * that moment is still pending/assigned/completed.
     */
    protected static function booted(): void
    {
        static::updating(function (self $maintenance) {
            if ($maintenance->getOriginal('status') === self::STATUS_APPROVED) {
                throw new \DomainException(
                    'Cette maintenance est déjà signée électroniquement et ne peut plus être modifiée.'
                );
            }
        });

        static::deleting(function (self $maintenance) {
            if ($maintenance->status === self::STATUS_APPROVED) {
                throw new \DomainException(
                    'Cette maintenance est déjà signée électroniquement et ne peut plus être supprimée.'
                );
            }
        });
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

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

    /**
     * Custom facture line items (parts / oil / labour) recorded during the
     * maintenance — mirrors the "BON AMC TRAVAUX" work order table.
     */
    public function items(): HasMany
    {
        return $this->hasMany(MaintenanceItem::class)->orderBy('position');
    }

    public function itemsTotal(): float
    {
        return (float) $this->items->sum('line_total');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }
}
