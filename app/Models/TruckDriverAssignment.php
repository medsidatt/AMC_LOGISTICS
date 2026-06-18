<?php

namespace App\Models;

use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TruckDriverAssignment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public const ROLE_TITULAIRE = 'titulaire';
    public const ROLE_ASSISTANT = 'assistant';

    public const ROLE_LABELS = [
        self::ROLE_TITULAIRE => 'Titulaire',
        self::ROLE_ASSISTANT => 'Assistant',
    ];

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('ended_at');
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
