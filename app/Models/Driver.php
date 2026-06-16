<?php

namespace App\Models;

use App\Http\Traits\TracksActions;
use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Propaganistas\LaravelPhone\PhoneNumber;

class Driver extends Model
{
    use SoftDeletes, TracksActions;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'whatsapp_opt_in_at' => 'datetime',
    ];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /**
     * Active drivers who can receive WhatsApp notifications: have a phone
     * number AND have opted in. Phone format validity is checked at send-time.
     */
    public function scopeNotifiable(Builder $q): Builder
    {
        return $q->where('is_active', true)
            ->whereNotNull('phone')
            ->whereNotNull('whatsapp_opt_in_at');
    }

    /**
     * Normalize the stored phone to E.164 digits-only (no leading "+"),
     * which is what Meta's Cloud API expects in the `to` field. Returns
     * null if the stored phone can't be parsed.
     */
    public function getWhatsappE164Attribute(): ?string
    {
        if (! $this->phone) {
            return null;
        }

        try {
            $country = (string) config('services.whatsapp.default_country', 'MR');
            $e164 = (new PhoneNumber($this->phone, $country))->formatE164();
            return ltrim($e164, '+');
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The truck this driver is currently assigned to ("camion assigné").
     */
    public function currentTruck(): BelongsTo
    {
        return $this->belongsTo(Truck::class, 'current_truck_id');
    }

    public function transportTrackings(): HasMany
    {
        return $this->hasMany(TransportTracking::class);
    }

    public function dailyChecklists(): HasMany
    {
        return $this->hasMany(DailyChecklist::class);
    }

    public function disciplineRecords(): HasMany
    {
        return $this->hasMany(DriverDisciplineRecord::class);
    }

    public function dailyDispatches(): HasMany
    {
        return $this->hasMany(DailyDispatch::class);
    }
}
