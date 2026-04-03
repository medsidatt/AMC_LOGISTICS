<?php

namespace App\Models;

use App\Http\Traits\TracksActions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transporter extends Model
{
    use SoftDeletes, TracksActions;

    protected $fillable = [
        'name',
        'address',
        'phone',
        'email',
        'website',
    ];

    public function trucks(): HasMany
    {
        return $this->hasMany(Truck::class);
    }
}
