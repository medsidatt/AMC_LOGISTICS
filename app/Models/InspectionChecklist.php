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

    const INSPECTION_FIELDS = [
        'seatbelts',
        'extinguisher_status',
        'first_aid_kit',
        'reflective_triangles',
        'tire_tread_depth',
        'brake_test_result',
        'lights_full_check',
        'mirrors',
        'horn',
        'steering_play',
        'suspension',
        'exhaust_emissions',
        'chassis_condition',
        'cargo_securing_equipment',
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
