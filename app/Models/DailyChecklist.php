<?php

namespace App\Models;

use App\Models\Auth\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DailyChecklist extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'fuel_refill' => 'boolean',
        'start_km' => 'float',
        'end_km' => 'float',
        'fuel_filled' => 'float',
        'checklist_date' => 'date',
        'week_start_date' => 'date',
        'validated_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_VALIDATED = 'validated';
    const STATUS_REJECTED = 'rejected';

    public static function weekStartFor(Carbon $date): Carbon
    {
        return $date->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
    }

    // ── Standardized options for analytics ──

    const TIRE_OPTIONS = [
        'bon' => 'Bon',
        'acceptable' => 'Acceptable',
        'use' => 'Use',
        'a_remplacer' => 'A remplacer',
        'crevee' => 'Crevee',
    ];

    const BRAKE_OPTIONS = [
        'bon' => 'Bon',
        'acceptable' => 'Acceptable',
        'mou' => 'Mou',
        'bruit_anormal' => 'Bruit anormal',
        'defaillant' => 'Defaillant',
    ];

    const LIGHT_OPTIONS = [
        'tous_fonctionnels' => 'Tous fonctionnels',
        'phare_defaillant' => 'Phare defaillant',
        'clignotant_defaillant' => 'Clignotant defaillant',
        'feu_arriere_defaillant' => 'Feu arriere defaillant',
        'plusieurs_defaillants' => 'Plusieurs defaillants',
        'aucun_fonctionnel' => 'Aucun fonctionnel',
    ];

    const OIL_LEVEL_OPTIONS = [
        'plein' => 'Plein',
        'correct' => 'Correct',
        'bas' => 'Bas',
        'critique' => 'Critique',
    ];

    const FUEL_LEVEL_OPTIONS = [
        'plein' => 'Plein',
        'trois_quarts' => '3/4',
        'demi' => '1/2',
        'quart' => '1/4',
        'reserve' => 'Reserve',
        'vide' => 'Vide',
    ];

    const GENERAL_CONDITION_OPTIONS = [
        'excellent' => 'Excellent',
        'bon' => 'Bon',
        'acceptable' => 'Acceptable',
        'mauvais' => 'Mauvais',
        'hors_service' => 'Hors service',
    ];

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function transportTracking(): BelongsTo
    {
        return $this->belongsTo(TransportTracking::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(DailyChecklistIssue::class);
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function scopeForWeek(Builder $query, int $truckId, Carbon $date): Builder
    {
        return $query->where('truck_id', $truckId)
            ->whereDate('week_start_date', self::weekStartFor($date)->toDateString());
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
