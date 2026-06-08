<?php

namespace App\Services;

use App\Models\DailyDispatch;
use App\Models\DailyDispatchEvent;
use App\Models\TripSegment;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Estimate the dispatch's expected arrival at the destination (client when
 * outbound, base when returning). Uses historical TripSegment medians for the
 * exact (origin_place_id, destination_place_id) pair when available; falls
 * back to corridor averages otherwise.
 *
 * Read-only: returns a Carbon or null.
 */
class DispatchEtaEstimator
{
    /** Median minutes Thiès → Rosso for a laden truck (corridor fallback). */
    private const FALLBACK_OUTBOUND_MINUTES = 540; // ~9h
    /** Median minutes Rosso → base when returning empty. */
    private const FALLBACK_RETURN_MINUTES = 420;   // ~7h

    public function estimate(DailyDispatch $dispatch, Collection $events): ?Carbon
    {
        // Find the latest segment-defining event
        $loadedAndLeft = $events->where('type', DailyDispatchEvent::TYPE_LOADED_AND_LEFT)
            ->sortByDesc('occurred_at')->first();
        $unloaded = $events->where('type', DailyDispatchEvent::TYPE_UNLOADED)
            ->sortByDesc('occurred_at')->first();

        $isReturn = $unloaded && (! $loadedAndLeft || $unloaded->occurred_at > $loadedAndLeft->occurred_at);
        $anchor = $isReturn ? $unloaded : $loadedAndLeft;

        if (! $anchor) {
            return null;
        }

        $originPlaceId = $anchor->place_id;
        $destinationType = $isReturn ? 'base' : 'client_site';

        $median = $this->medianMinutesForLeg($originPlaceId, $destinationType);
        if ($median === null) {
            $median = $isReturn ? self::FALLBACK_RETURN_MINUTES : self::FALLBACK_OUTBOUND_MINUTES;
        }

        return Carbon::parse($anchor->occurred_at)->addMinutes((int) round($median));
    }

    /**
     * Median minutes for the (origin_place_id, destination_type) leg, computed
     * over the last 90 days from validated TripSegment rows. Cached 6h.
     */
    private function medianMinutesForLeg(?int $originPlaceId, string $destinationType): ?float
    {
        if (! $originPlaceId) {
            return null;
        }

        $cacheKey = "eta:leg:{$originPlaceId}:{$destinationType}";

        return Cache::remember($cacheKey, 6 * 3600, function () use ($originPlaceId, $destinationType) {
            $durations = TripSegment::query()
                ->linked()
                ->where('origin_place_id', $originPlaceId)
                ->whereHas('destinationPlace', fn ($q) => $q->where('type', $destinationType))
                ->whereNotNull('ended_at')
                ->where('started_at', '>=', now()->subDays(90))
                ->get()
                ->map(fn ($s) => $s->ended_at && $s->started_at
                    ? abs($s->started_at->diffInMinutes($s->ended_at, false))
                    : null)
                ->filter()
                ->values();

            if ($durations->isEmpty()) {
                return null;
            }

            $sorted = $durations->sort()->values();
            $count = $sorted->count();
            $mid = (int) floor($count / 2);

            return $count % 2 === 0
                ? ($sorted[$mid - 1] + $sorted[$mid]) / 2
                : $sorted[$mid];
        });
    }
}
