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
 * Matching window: provider_date within ±1 day of loaded_at's date, same
 * truck, same provider. provider_date is a DATE-only column (no time), so we
 * match on the calendar day rather than a time window.
 */
class TicketReconciliationService
{
    /** Day tolerance for matching a TransportTracking to an expected loading. */
    private const MATCH_WINDOW_DAYS = 1;

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
        $loadedDate = Carbon::parse($expected->loaded_at)->toDateString();

        // Match by (truck, provider) on the calendar day. provider_date is a
        // DATE-only column reflecting when the load was weighed at the quarry,
        // so we match within ±1 day of loaded_at's date and disambiguate
        // multiple same-window candidates by the nearest provider_date.
        $match = TransportTracking::query()
            ->where('truck_id', $expected->truck_id)
            ->where('provider_id', $expected->provider_id)
            ->whereBetween('provider_date', [
                Carbon::parse($loadedDate)->subDays(self::MATCH_WINDOW_DAYS)->toDateString(),
                Carbon::parse($loadedDate)->addDays(self::MATCH_WINDOW_DAYS)->toDateString(),
            ])
            ->whereDoesntHave('expectedTicket')
            ->orderByRaw('ABS(DATEDIFF(provider_date, ?))', [$loadedDate])
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
