<?php

namespace App\Models;

use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InspectionChecklist extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'inspection_date' => 'date',
        'validated_at' => 'datetime',
    ];

    const CATEGORY_OPTIONS = [
        'safety' => 'Sécurité',
        'compliance' => 'Conformité',
        'mechanical' => 'Mécanique',
        'comprehensive' => 'Complète',
    ];

    const CONDITION_OPTIONS = [
        'ok' => 'OK',
        'needs_attention' => 'À surveiller',
        'critical' => 'Critique',
        'na' => 'N/A',
    ];

    const STATUS_DRAFT = 'draft';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_VALIDATED = 'validated';
    const STATUS_REJECTED = 'rejected';

    const SECTIONS = [
        'general' => [
            'label' => 'État général du véhicule',
            'fields' => [
                'cleanliness' => 'Propreté générale',
                'visible_damage_check' => 'Absence de dommage visible',
                'chassis_condition' => 'État du châssis',
                'dump_body_cracks_check' => 'Absence de fissure sur la benne',
            ],
        ],
        'engine' => [
            'label' => 'Moteur et fonctionnement',
            'fields' => [
                'oil_level' => 'Niveau d\'huile moteur',
                'coolant_level' => 'Niveau de liquide de refroidissement',
                'fuel_level_check' => 'Niveau carburant',
                'exhaust_emissions' => 'Absence de fumée anormale',
                'engine_noise' => 'Absence de bruit anormal',
            ],
        ],
        'hydraulics' => [
            'label' => 'Système hydraulique',
            'fields' => [
                'hydraulic_cylinder' => 'Vérin hydraulique en bon état',
                'hydraulic_oil_leak' => 'Absence de fuite d\'huile hydraulique',
                'dump_lift_function' => 'Fonctionnement du levage de la benne',
                'dump_descent_function' => 'Descente correcte de la benne',
                'hydraulic_hose' => 'Flexible hydraulique en bon état',
            ],
        ],
        'dump_body' => [
            'label' => 'Benne',
            'fields' => [
                'dump_body_condition' => 'État général de la benne',
                'dump_body_locking' => 'Verrouillage de la benne',
                'dump_body_tarp' => 'Bâche de protection',
                'dump_body_ridelle' => 'Ridelle en bon état',
                'cargo_securing_equipment' => 'Équipement d\'arrimage',
            ],
        ],
        'braking_steering' => [
            'label' => 'Freinage et direction',
            'fields' => [
                'brake_test_result' => 'Frein de service',
                'parking_brake' => 'Frein de stationnement',
                'steering_play' => 'Direction normale',
                'suspension' => 'Suspension en bon état',
            ],
        ],
        'tires' => [
            'label' => 'Pneumatique',
            'fields' => [
                'tire_tread_depth' => 'État des pneus (sculptures)',
                'tire_pressure' => 'Pression correcte',
                'tire_cuts' => 'Absence de coupure',
                'spare_tire' => 'Roue de secours',
            ],
        ],
        'signaling' => [
            'label' => 'Signalisation et sécurité',
            'fields' => [
                'lights_full_check' => 'Feux (avant/arrière/clignotants/stop)',
                'beacon_light' => 'Gyrophare',
                'reverse_alarm' => 'Alarme de recul',
                'horn' => 'Klaxon',
            ],
        ],
        'safety_equipment' => [
            'label' => 'Équipement de sécurité',
            'fields' => [
                'extinguisher_status' => 'Extincteur disponible',
                'reflective_triangles' => 'Triangle de signalisation',
                'safety_vest' => 'Gilet de sécurité',
                'wheel_chocks' => 'Cales de roues',
                'seatbelts' => 'Ceinture de sécurité conducteur',
                'passenger_seatbelt' => 'Ceinture de sécurité passager',
                'first_aid_kit' => 'Trousse de secours',
            ],
        ],
        'cabin' => [
            'label' => 'Cabine',
            'fields' => [
                'mirrors' => 'Rétroviseurs en bon état',
                'dashboard_indicators' => 'Tableau de bord fonctionnel',
                'wipers' => 'Essuie-glaces',
            ],
        ],
    ];

    public static function inspectionFields(): array
    {
        $out = [];
        foreach (self::SECTIONS as $section) {
            foreach (array_keys($section['fields']) as $field) {
                $out[] = $field;
            }
        }
        return $out;
    }

    const INSPECTION_FIELDS = [
        'seatbelts', 'extinguisher_status', 'first_aid_kit', 'reflective_triangles',
        'tire_tread_depth', 'brake_test_result', 'lights_full_check', 'mirrors',
        'horn', 'steering_play', 'suspension', 'exhaust_emissions', 'chassis_condition',
        'cargo_securing_equipment',
        'cleanliness', 'visible_damage_check', 'dump_body_cracks_check',
        'oil_level', 'coolant_level', 'fuel_level_check', 'engine_noise',
        'hydraulic_cylinder', 'hydraulic_oil_leak', 'dump_lift_function',
        'dump_descent_function', 'hydraulic_hose',
        'dump_body_condition', 'dump_body_locking', 'dump_body_tarp', 'dump_body_ridelle',
        'parking_brake',
        'tire_pressure', 'tire_cuts', 'spare_tire',
        'beacon_light', 'reverse_alarm',
        'safety_vest', 'wheel_chocks', 'passenger_seatbelt',
        'dashboard_indicators', 'wipers',
    ];

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(InspectionChecklistIssue::class);
    }

    public function scopePendingValidation(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SUBMITTED);
    }
}
