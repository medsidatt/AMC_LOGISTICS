<?php

namespace App\Domain\Analytics\Fuel;

use App\Domain\Operations\Contracts\FleetiConsumptionReadModelInterface;
use App\Domain\Operations\Contracts\FuelReadModelInterface;
use App\Domain\Operations\ReadModels\Data\FuelImportBatchProjection;
use App\Domain\Operations\ReadModels\Data\FuelSourceSlice;
use App\Domain\Operations\ReadModels\Data\MonthlyConsumptionPoint;
use App\Domain\Operations\ReadModels\Data\MonthlyFuelSpendPoint;
use App\Domain\Operations\ReadModels\Data\TruckConsumptionProjection;
use App\Domain\Operations\ReadModels\Data\TruckFuelProjection;
use DateTimeImmutable;

/**
 * DESCRIPTIVE fuel dashboard data provider — the presentation composition for fuel analytics.
 *
 * It composes the Fuel and Fleeti-Consumption Read Models into one dashboard payload and does
 * NOTHING else: no KPI registry, no threshold, no target, no score, no alert, no exception
 * detection, no good/bad classification, no performance colour. Every value is a stored fact or
 * a plain descriptive aggregation of stored facts. Trend arrays are chart series, not verdicts;
 * the reconciliation section juxtaposes the two sources per month without judging them.
 *
 * Deliberately registry-independent: the BI Command Centers are keyed on BusinessKpiId and the
 * fuel KPI catalog is frozen — this provider must not create that coupling.
 */
class FuelDashboardDataProvider
{
    public function __construct(
        private readonly FuelReadModelInterface $fuel,
        private readonly FleetiConsumptionReadModelInterface $consumption,
    ) {}

    /** @return array<string, mixed> presentation-ready descriptive payload */
    public function dashboard(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $truckSpend = $this->fuel->truckFuelSpend($from, $to);
        $truckConsumption = $this->consumption->truckConsumption($from, $to);
        $monthlySpend = $this->fuel->monthlySpend($from, $to);
        $monthlyConsumption = $this->consumption->monthlyConsumption($from, $to);
        $review = $this->fuel->reviewQueueStats();
        $history = $this->fuel->importHistory();

        $consumptionByTruck = $truckConsumption->keyBy(fn (TruckConsumptionProjection $c) => $c->truckId);

        return [
            'period' => ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')],

            // Headline totals — plain sums of stored facts.
            'totals' => [
                'spend_fcfa' => round($truckSpend->sum(fn (TruckFuelProjection $t) => $t->totalSpendFcfa), 2),
                'estimated_litres' => round($truckSpend->sum(fn (TruckFuelProjection $t) => $t->estimatedLitres), 2),
                'recharge_count' => $truckSpend->sum(fn (TruckFuelProjection $t) => $t->rechargeCount),
                'consumed_litres' => round($truckConsumption->sum(fn (TruckConsumptionProjection $c) => $c->consumedLitres), 2),
                'kilometers' => round($truckConsumption->sum(fn (TruckConsumptionProjection $c) => $c->kilometers), 2),
                'refills_volume' => round($truckConsumption->sum(fn (TruckConsumptionProjection $c) => $c->refillsVolume), 2),
                'trucks_with_fuel' => $truckSpend->filter(fn (TruckFuelProjection $t) => $t->rechargeCount > 0)->count(),
                'imported_files' => $history->count(),
            ],

            // Monthly chart series (oldest → newest) — stored monthly sums, no verdict.
            'monthly_spend' => $monthlySpend->map(fn (MonthlyFuelSpendPoint $p) => [
                'month' => $p->month,
                'recharges' => $p->rechargeCount,
                'spend_fcfa' => $p->spendFcfa,
                'estimated_litres' => $p->estimatedLitres,
            ])->values()->all(),
            'monthly_consumption' => $monthlyConsumption->map(fn (MonthlyConsumptionPoint $p) => [
                'month' => $p->month,
                'recorded_days' => $p->recordedDays,
                'kilometers' => $p->kilometers,
                'consumed_litres' => $p->consumedLitres,
                'refills_volume' => $p->refillsVolume,
            ])->values()->all(),

            // Per-truck distribution — descriptive ordering by magnitude (a sort, not a ranking verdict).
            'by_truck' => $truckSpend
                ->filter(fn (TruckFuelProjection $t) => $t->rechargeCount > 0 || ($consumptionByTruck[$t->truckId]->recordedDays ?? 0) > 0)
                ->sortByDesc(fn (TruckFuelProjection $t) => $t->totalSpendFcfa)
                ->map(function (TruckFuelProjection $t) use ($consumptionByTruck) {
                    $c = $consumptionByTruck[$t->truckId] ?? null;

                    return [
                        'truck_id' => $t->truckId,
                        'matricule' => $t->matricule,
                        'recharges' => $t->rechargeCount,
                        'spend_fcfa' => $t->totalSpendFcfa,
                        'estimated_litres' => $t->estimatedLitres,
                        'last_recharge_at' => $t->lastRechargeAt?->format('Y-m-d H:i:s'),
                        'recorded_days' => $c?->recordedDays ?? 0,
                        'kilometers' => $c?->kilometers ?? 0.0,
                        'consumed_litres' => $c?->consumedLitres ?? 0.0,
                        'refills_volume' => $c?->refillsVolume ?? 0.0,
                    ];
                })->values()->all(),

            // Source distribution — stored (source, type) slices as-is.
            'source_distribution' => $this->fuel->sourceDistribution($from, $to)
                ->map(fn (FuelSourceSlice $s) => [
                    'source' => $s->source,
                    'transaction_type' => $s->transactionType,
                    'count' => $s->transactionCount,
                    'amount_fcfa' => $s->amountFcfa,
                ])->values()->all(),

            // Review queue — stored review_status tallies.
            'review_queue' => [
                'pending' => $review->pending,
                'resolved' => $review->resolved,
                'none' => $review->none,
                'oldest_pending_at' => $review->oldestPendingAt,
            ],

            // Import history — the stored batch audit trail, newest first.
            'import_history' => $history->map(fn (FuelImportBatchProjection $b) => [
                'batch_id' => $b->batchId,
                'filename' => $b->filename,
                'source' => $b->source,
                'total_rows' => $b->totalRows,
                'accepted_rows' => $b->acceptedRows,
                'rejected_rows' => $b->rejectedRows,
                'imported_at' => $b->importedAt,
            ])->values()->all(),

            // Reconciliation — per-month juxtaposition of the two independent sources.
            // Both series shown side by side; no delta verdict, no tolerance, no flag.
            'reconciliation' => $this->reconcile($monthlySpend, $monthlyConsumption),
        ];
    }

    /**
     * Merge the two monthly series on month for side-by-side display.
     *
     * @param  \Illuminate\Support\Collection<int, MonthlyFuelSpendPoint>  $spend
     * @param  \Illuminate\Support\Collection<int, MonthlyConsumptionPoint>  $consumption
     * @return list<array<string, mixed>>
     */
    private function reconcile($spend, $consumption): array
    {
        $spendByMonth = $spend->keyBy(fn (MonthlyFuelSpendPoint $p) => $p->month);
        $consByMonth = $consumption->keyBy(fn (MonthlyConsumptionPoint $p) => $p->month);

        return $spendByMonth->keys()
            ->merge($consByMonth->keys())
            ->unique()
            ->sort()
            ->map(fn (string $month) => [
                'month' => $month,
                'edk_estimated_litres' => $spendByMonth[$month]->estimatedLitres ?? null,
                'edk_spend_fcfa' => $spendByMonth[$month]->spendFcfa ?? null,
                'fleeti_consumed_litres' => $consByMonth[$month]->consumedLitres ?? null,
                'fleeti_recorded_days' => $consByMonth[$month]->recordedDays ?? null,
                'both_sources_present' => isset($spendByMonth[$month], $consByMonth[$month]),
            ])->values()->all();
    }
}
