<?php

namespace App\Services;

use App\Models\ExpectedTransportTicket;
use App\Models\TransportTracking;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Reconciles ExpectedTransportTicket rows (one per GPS-observed quarry loading)
 * against TransportTracking rows the Logistics team registers. Resolves the
 * under-ticketing gap surfaced in operational reviews (e.g. March 2026:
 * 34 GPS CSE visits vs 13 CSE tickets — see memory).
 *
 * Matching window: |provider_date - loaded_at| <= 2h, same truck, same provider.
 */
class TicketReconciliationService
{
    /** Time window for matching a TransportTracking to an expected loading. */
    private const MATCH_WINDOW_HOURS = 2;

    /**
     * Run reconciliation across all open expected tickets.
     *
     * @return array{matched: int, missing: int, kept_expected: int}
     */
    public function reconcileAll(): array
    {
        $stats = ['matched' => 0, 'missing' => 0, 'kept_expected' => 0];

        $candidates = ExpectedTransportTicket::query()
            ->whereIn('status', [ExpectedTransportTicket::STATUS_EXPECTED, ExpectedTransportTicket::STATUS_MISSING])
            ->get();

        foreach ($candidates as $expected) {
            $result = $this->reconcileOne($expected);
            $stats[$result] = ($stats[$result] ?? 0) + 1;
        }

        return $stats;
    }

    /**
     * Reconcile a single ExpectedTransportTicket.
     *
     * @return string  'matched' | 'missing' | 'kept_expected'
     */
    public function reconcileOne(ExpectedTransportTicket $expected): string
    {
        $loadedAt = Carbon::parse($expected->loaded_at);
        $start = $loadedAt->copy()->subHours(self::MATCH_WINDOW_HOURS);
        $end = $loadedAt->copy()->addHours(self::MATCH_WINDOW_HOURS);

        // Match by (truck, provider) within the time window.
        // TransportTracking has provider_date that reflects when the load was
        // weighed at the quarry — the natural anchor for matching.
        $match = TransportTracking::query()
            ->where('truck_id', $expected->truck_id)
            ->where('provider_id', $expected->provider_id)
            ->whereBetween('provider_date', [$start, $end])
            ->whereDoesntHave('expectedTicket')
            ->orderBy('provider_date')
            ->first();

        if ($match) {
            try {
                $expected->update([
                    'status' => ExpectedTransportTicket::STATUS_MATCHED,
                    'transport_tracking_id' => $match->id,
                ]);
                return 'matched';
            } catch (\Throwable $e) {
                Log::warning('Failed to mark expected ticket as matched', [
                    'expected_id' => $expected->id,
                    'tracking_id' => $match->id,
                    'error' => $e->getMessage(),
                ]);
                return 'kept_expected';
            }
        }

        // No match yet — flag as missing once the deadline has passed.
        if (Carbon::now()->gte($expected->deadline_at) && $expected->status !== ExpectedTransportTicket::STATUS_MISSING) {
            $expected->update(['status' => ExpectedTransportTicket::STATUS_MISSING]);
            return 'missing';
        }

        return 'kept_expected';
    }
}
