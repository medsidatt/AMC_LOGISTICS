<?php

namespace App\Services\Fuel;

use App\Models\FleetiDailyRecord;
use App\Models\FuelCardTransaction;
use App\Models\Truck;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FuelComparisonService
{
    /**
     * Monthly card-float / budget-vs-burn rows for a truck: EDK money recharged (and its ESTIMATED
     * litres = amount ÷ price) vs Fleeti litres consumed. This is NOT a purchased-vs-consumed
     * reconciliation — EDK is a recharge ledger with no measured volume (docs/fuel-edk-reclassification.md).
     *
     * @return array<int, array{
     *     month: string,
     *     month_label: string,
     *     edk_litres: float,
     *     edk_fcfa: float,
     *     fleeti_litres: float,
     *     gap_litres: float,
     *     gap_pct: float|null,
     *     km: float,
     *     l_per_100km: float|null,
     * }>
     */
    public function forTruck(Truck $truck, int $monthsBack = 12): array
    {
        $start = now()->subMonths($monthsBack)->startOfMonth();

        $edk = FuelCardTransaction::query()
            ->select(
                DB::raw("DATE_FORMAT(occurred_at, '%Y-%m') as ym"),
                DB::raw('SUM(estimated_litres) as litres'),
                DB::raw('SUM(amount_fcfa) as fcfa'),
            )
            ->where('truck_id', $truck->id)
            ->where('occurred_at', '>=', $start)
            ->groupBy('ym')
            ->get()
            ->keyBy('ym');

        $fleeti = FleetiDailyRecord::query()
            ->select(
                DB::raw("DATE_FORMAT(record_date, '%Y-%m') as ym"),
                DB::raw('SUM(consumed) as litres'),
                DB::raw('SUM(kilometers) as km'),
            )
            ->where('truck_id', $truck->id)
            ->where('record_date', '>=', $start->toDateString())
            ->groupBy('ym')
            ->get()
            ->keyBy('ym');

        $months = collect($edk->keys())->merge($fleeti->keys())->unique()->sort()->values();

        $out = [];
        foreach ($months as $ym) {
            $edkRow = $edk->get($ym);
            $fleetiRow = $fleeti->get($ym);
            $edkLitres = (float) ($edkRow->litres ?? 0);
            $edkFcfa = (float) ($edkRow->fcfa ?? 0);
            $fleetiLitres = (float) ($fleetiRow->litres ?? 0);
            $km = (float) ($fleetiRow->km ?? 0);

            $gap = round($edkLitres - $fleetiLitres, 2);
            $gapPct = $fleetiLitres > 0 ? round(($gap / $fleetiLitres) * 100, 1) : null;
            $lPer100 = $km > 0 ? round(($fleetiLitres / $km) * 100, 2) : null;
            $monthLabel = Carbon::createFromFormat('Y-m', $ym)->locale('fr')->isoFormat('MMM YYYY');

            $out[] = [
                'month' => $ym,
                'month_label' => $monthLabel,
                'edk_litres' => round($edkLitres, 2),
                'edk_fcfa' => round($edkFcfa, 0),
                'fleeti_litres' => round($fleetiLitres, 2),
                'gap_litres' => $gap,
                'gap_pct' => $gapPct,
                'km' => round($km, 2),
                'l_per_100km' => $lPer100,
            ];
        }

        return $out;
    }
}
