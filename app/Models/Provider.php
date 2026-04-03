<?php

namespace App\Models;

use App\Http\Traits\TracksActions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Provider extends Model
{
    use SoftDeletes, TracksActions;

    protected $guarded = [];

    public function transportTrackings() {
        return $this->hasMany(TransportTracking::class);
    }

}
