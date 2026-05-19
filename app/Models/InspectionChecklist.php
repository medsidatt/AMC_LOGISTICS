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
        'field_remarks' => 'array',
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

    // HSE/compliance scope only. Mechanical health (engine, hydraulics, full dump-body,
    // suspension, steering, tire pressure) is recorded on the Maintenance model instead
    // so the Logistics Responsible doesn't fill in the same information twice.
    const SECTIONS = [
        'general' => [
            'label' => 'État général du véhicule',
            'fields' => [
                'cleanliness' => 'Propreté générale',
                'visible_damage_check' => 'Absence de dommage visible',
            ],
        ],
        'brakes' => [
            'label' => 'Freinage',
            'fields' => [
                'brake_test_result' => 'Frein de service',
                'parking_brake' => 'Frein de stationnement',
            ],
        ],
        'tires' => [
            'label' => 'Pneumatique',
            'fields' => [
                'tire_tread_depth' => 'État des pneus (sculptures)',
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
                'cabine_fermee' => 'Cabine entièrement fermée',
                'mirrors' => 'Rétroviseurs en bon état',
                'parebrise_vitres' => 'Pare-brise et vitres en bon état',
                'dashboard_indicators' => 'Tableau de bord fonctionnel',
                'wipers' => 'Essuie-glaces',
            ],
        ],
        'documents' => [
            'label' => 'Identification & documents',
            'fields' => [
                'immatriculation_visible' => 'Numéro d\'immatriculation visible',
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

    // Mirror of SECTIONS in flat form, in display order, used by the validator and the
    // generic Show.tsx renderer. Mechanical fields removed; see SECTIONS comment.
    const INSPECTION_FIELDS = [
        // general
        'cleanliness', 'visible_damage_check',
        // brakes
        'brake_test_result', 'parking_brake',
        // tires
        'tire_tread_depth', 'tire_cuts', 'spare_tire',
        // signaling
        'lights_full_check', 'beacon_light', 'reverse_alarm', 'horn',
        // safety_equipment
        'extinguisher_status', 'reflective_triangles', 'safety_vest', 'wheel_chocks',
        'seatbelts', 'passenger_seatbelt', 'first_aid_kit',
        // cabin
        'cabine_fermee', 'mirrors', 'parebrise_vitres', 'dashboard_indicators', 'wipers',
        // documents
        'immatriculation_visible',
    ];

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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
