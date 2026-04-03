<?php

namespace App\Repositories;

use App\Models\Truck;
use Illuminate\Support\Collection;

class TruckRepository
{
    public function findByMatricule(string $matricule): ?Truck
    {
        return Truck::where('matricule', $matricule)->first();
    }

    public function findByMatriculeOrFail(string $matricule): Truck
    {
        return Truck::where('matricule', $matricule)->firstOrFail();
    }

    public function getAllForFleetiMatching(): Collection
    {
        return Truck::query()->get();
    }

    public function getTrucksRequiringFleetiSync(int $intervalMinutes): Collection
    {
        return Truck::query()
            ->where('maintenance_type', 'kilometers')
            ->where(function ($query) use ($intervalMinutes) {
                $query->whereNull('fleeti_last_synced_at')
                    ->orWhere('fleeti_last_synced_at', '<=', now()->subMinutes($intervalMinutes));
            })
            ->get();
    }
}
