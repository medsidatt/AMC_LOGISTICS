<?php

namespace App\Services;

use App\Models\Place;
use App\Models\TripSegment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Detects "freight loops" from GPS trip segments — the quarry→client→return
 * rotation cycle. A loop is 3 consecutive segments for one truck:
 *   1. PARKING        -> PROVIDER_SITE (quarry)   (empty, going to load)
 *   2. PROVIDER_SITE  -> CLIENT_SITE              (loaded leg)
 *   3. CLIENT_SITE    -> PARKING                  (empty, returning)
 *
 * This is the single source of truth for the loop shape — reused by both
 * UntrackedTripDetector (under-ticketing incidents) and RotationAchievementService.
 */
class FreightLoopService
{
    public function matchesFreightLoop(TripSegment $a, TripSegment $b, TripSegment $c): bool
    {
        if (! $a->originPlace || ! $a->destinationPlace) return false;
        if (! $b->originPlace || ! $b->destinationPlace) return false;
        if (! $c->originPlace || ! $c->destinationPlace) return false;

        return $a->originPlace->type === Place::TYPE_PARKING
            && $a->destinationPlace->type === Place::TYPE_PROVIDER_SITE
            && $b->originPlace->id === $a->destinationPlace->id
            && $b->destinationPlace->type === Place::TYPE_CLIENT_SITE
            && $c->originPlace->id === $b->destinationPlace->id
            && $c->destinationPlace->type === Place::TYPE_PARKING;
    }

    /**
     * Freight loops that COMPLETED (returned to parking) within [start, end].
     * Returns a collection of arrays:
     *   truck_id, ended_at (Carbon), date (Y-m-d), provider_place_id,
     *   client_place_id, segment_ids[], transport_tracking_id|null, distance_km.
     */
    public function loopsForPeriod(Carbon $start, Carbon $end, ?int $truckId = null): Collection
    {
        $periodStart = $start->copy()->startOfDay();
        $periodEnd = $end->copy()->endOfDay();

        $query = TripSegment::query()
            ->with(['originPlace:id,type', 'destinationPlace:id,type'])
            // Pull a few days before so loops that started earlier but ended
            // in-period are matched whole.
            ->where('ended_at', '>=', $periodStart->copy()->subDays(3))
            ->where('ended_at', '<=', $periodEnd)
            ->orderBy('truck_id')->orderBy('started_at')->orderBy('id');

        if ($truckId) {
            $query->where('truck_id', $truckId);
        }

        $loops = collect();

        foreach ($query->get()->groupBy('truck_id') as $tId => $segments) {
            $segments = $segments->values();
            for ($i = 0; $i + 2 < $segments->count(); $i++) {
                $a = $segments[$i];
                $b = $segments[$i + 1];
                $c = $segments[$i + 2];

                if (! $this->matchesFreightLoop($a, $b, $c)) {
                    continue;
                }

                $endedAt = $c->ended_at;
                if (! $endedAt || $endedAt->lt($periodStart) || $endedAt->gt($periodEnd)) {
                    continue;
                }

                $loops->push([
                    'truck_id' => (int) $tId,
                    'ended_at' => $endedAt,
                    'date' => $endedAt->toDateString(),
                    'provider_place_id' => $a->destination_place_id,
                    'client_place_id' => $b->destination_place_id,
                    'segment_ids' => [$a->id, $b->id, $c->id],
                    'transport_tracking_id' => $a->transport_tracking_id ?: $b->transport_tracking_id ?: $c->transport_tracking_id,
                    'distance_km' => round((float) ($a->distance_km ?? 0) + (float) ($b->distance_km ?? 0) + (float) ($c->distance_km ?? 0), 2),
                ]);

                $i += 2; // advance past this loop
            }
        }

        return $loops;
    }
}
