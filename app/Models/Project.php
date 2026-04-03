<?php

namespace App\Models;

use App\Http\Traits\TracksActions;
use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Project extends Model
{
    use SoftDeletes, TracksActions;

    protected $fillable = [
        'name',
        'code',
        'entity_id',
        'description',
        'start_date',
        'end_date',
        'matricule_cnss',
        'matricule_cnam',
        'bp',
        'address',
        'phone',
        'email',
        'website',
        'is_active',
        'logo',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public static function all($columns = ['*'])
    {
        $user = Auth::user();

        if (!$user) {
            return collect();
        }

        if ($user->hasRole('Super Admin')) {
            return parent::all($columns);
        }

        return parent::whereHas('users', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->select($columns)->get();
    }

    public function users()
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function scopeForCurrentUser($query)
    {
        $user = Auth::user();

        if (!$user) {
            return $query->whereRaw('0 = 1');
        }

        if ($user->hasRole('Super Admin')) {
            return $query;
        }

        return $query->whereHas('users', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        });
    }

    protected static function boot()
    {
        parent::boot();
    }
}
