<?php

namespace App\Models;

use App\Http\Traits\TracksActions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @OA\Schema(
 *     schema="TransportTracking",
 *     title="Transport Tracking",
 *     description="Transport Tracking model",
 *     @OA\Property(property="id", type="integer", description="ID"),
 *     @OA\Property(property="reference", type="string", description="Reference code"),
 *     @OA\Property(property="truck_id", type="integer", description="Truck ID"),
 *     @OA\Property(property="driver_id", type="integer", description="Driver ID"),
 *     @OA\Property(property="provider_id", type="integer", description="Provider ID"),
 *     @OA\Property(property="product", type="string", description="Product name"),
 *     @OA\Property(property="base", type="string", description="Base location"),
 *     @OA\Property(property="provider_net_weight", type="number", format="float", description="Provider net weight"),
 *     @OA\Property(property="client_net_weight", type="number", format="float", description="Client net weight"),
 *     @OA\Property(property="gap", type="number", format="float", description="Weight gap"),
 *     @OA\Property(property="provider_date", type="string", format="date", description="Provider date"),
 *     @OA\Property(property="client_date", type="string", format="date", description="Client date"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class TransportTracking extends Model
{
    use SoftDeletes, TracksActions;

    protected $guarded = [];

    protected $casts = [
        'provider_date' => 'date',
        'client_date' => 'date',
    ];

    // append column to table
//    protected $appends = ['gap'];

    // relations
    // driver
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class)->withTrashed();
    }

    // truck
    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class)->withTrashed();
    }

    // provider
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class)->withTrashed();
    }

    // gap
    /* public function getGapAttribute()
     {
         return $this->provider_net_weight - $this->client_net_weight;
     }*/


    protected static function boot(): void
    {

        parent::boot();

        static::creating(function ($model) {
            if (empty($model->reference)) {
                $model->reference = self::generateReference();
            }
        });

        static::saving(function ($model) {
            $model->gap = $model->client_net_weight - $model->provider_net_weight;
        });

    }

    public static function generateReference(): string
    {
        $next = 1;

        do {
            $reference = 'AMC' . str_pad($next, 5, '0', STR_PAD_LEFT);
            $exists = self::withTrashed()->where('reference', $reference)->exists();
            $next++;
        } while ($exists);

        return $reference;
    }


    public static function findExisting(array $data)
    {
        return self::query()
            ->when($data['provider_reference'] ?? null, function ($q, $ref) {
                $q->where('ref_provider', $ref);
            })
            ->when($data['client_reference'] ?? null, function ($q, $ref) {
                $q->orWhere('ref_client', $ref);
            })
            ->when(empty($data['provider_reference']) && empty($data['client_reference']), function ($q) use ($data) {
                // Only search by truck + product + base + one of the dates if no references
                $q->where('truck_id', $data['truck_id'])
                    ->where('product', $data['product'])
                    ->where('base', $data['base'])
                    ->where(function ($q2) use ($data) {
                        if (!empty($data['provider_date'])) {
                            $q2->whereDate('provider_date', $data['provider_date']);
                        }
                        if (!empty($data['client_date'])) {
                            $q2->orWhereDate('client_date', $data['client_date']);
                        }
                    });
            })
            ->first();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

}
