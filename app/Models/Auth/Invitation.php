<?php

namespace App\Models\Auth;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invitation extends Model
{
    use SoftDeletes;

    protected $fillable = ['email', 'role_name', 'token', 'expires_at', 'is_used'];

    /*public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }*/

    public function isExpired(): bool
    {
        $expiresAt = $this->expires_at ? Carbon::parse($this->expires_at) : null;

        return $expiresAt && $expiresAt->isPast();
    }
}
