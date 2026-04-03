<?php

namespace App\Models;

use App\Http\Traits\TracksActions;
use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Driver extends Model
{
    use SoftDeletes, TracksActions;

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transportTrackings(): HasMany
    {
        return $this->hasMany(TransportTracking::class);
    }

    public function dailyChecklists(): HasMany
    {
        return $this->hasMany(DailyChecklist::class);
    }
}
