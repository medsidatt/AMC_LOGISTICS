<?php

namespace App\Repositories;

use App\Models\DailyDispatch;
use App\Models\Truck;
use Illuminate\Support\Carbon;
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

    /**
     * Trucks that need fast-cadence polling because they are participating in
     * today's program — either explicitly on a sent DailyDispatch, or still
     * on the road from a previous-day dispatch, or with a recently active
     * trip not yet closed by an arrival event.
     */
    public function getTrucksOnDispatchToday(int $windowHours = 18): Collection
    {
        $today = Carbon::today()->toDateString();
        $yesterday = Carbon::yesterday()->toDateString();
        $since = now()->subHours($windowHours);

        $dispatchedTodayTruckIds = DailyDispatch::query()
            ->whereDate('dispatch_date', $today)
            ->notified()
            ->whereNotNull('truck_id')
            ->pluck('truck_id');

        $dispatchedYesterdayTruckIds = DailyDispatch::query()
            ->whereDate('dispatch_date', $yesterday)
            ->notified()
            ->whereNotNull('truck_id')
            ->where(function ($q) {
                $q->whereNull('current_status')
                    ->orWhere('current_status', '!=', DailyDispatch::STATUS_LIVE_TERMINE);
            })
            ->pluck('truck_id');

        $combinedTruckIds = $dispatchedTodayTruckIds
            ->merge($dispatchedYesterdayTruckIds)
            ->filter()
            ->unique()
            ->values();

        if ($combinedTruckIds->isEmpty()) {
            return Truck::query()->whereRaw('0=1')->get();
        }

        return Truck::query()
            ->whereIn('id', $combinedTruckIds)
            ->whereNotNull('fleeti_asset_id')
            ->where(function ($q) use ($since) {
                $q->whereNull('fleeti_device_last_seen_at')
                    ->orWhere('fleeti_device_last_seen_at', '>=', $since);
            })
            ->get();
    }
}
