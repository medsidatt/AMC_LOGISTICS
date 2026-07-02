<?php

namespace App\Domain\Operations\ReadModels;

use App\Domain\Operations\Contracts\FuelReadModelInterface;
use App\Domain\Operations\ReadModels\Data\FuelImportBatchProjection;
use App\Domain\Operations\ReadModels\Data\FuelReviewQueueStats;
use App\Domain\Operations\ReadModels\Data\FuelSourceSlice;
use App\Domain\Operations\ReadModels\Data\MonthlyFuelSpendPoint;
use App\Domain\Operations\ReadModels\Data\TruckFuelProjection;
use App\Enums\Fuel\ReviewStatus;
use App\Enums\Fuel\TransactionType;
use App\Models\FuelCardTransaction;
use App\Models\FuelImportBatch;
use App\Models\Truck;
use DateTimeImmutable;
use Illuminate\Support\Collection;

/**
 * Read-only projections over the active fleet's RAW fuel-recharge facts.
 *
 * A pure query layer: it aggregates the persisted `fuel_card_transactions` (FUEL_RECHARGE rows
 * attributed to a truck) into per-truck sums/counts and maps them directly. It applies NO
 * business rule — `transaction_type` and `kpi_eligible` are STORED facts written upstream by the
 * ClassificationPolicy at import; reading them here is not a decision. It derives no ratio, applies
 * no threshold, reads no parameter, and emits no event. Cost-per-tonne, litres/100km and budget
 * verdicts belong to a Domain Calculator, never to this Read Model.
 */
class FuelReadModel implements FuelReadModelInterface
{
    public function truckFuelSpend(DateTimeImmutable $from, DateTimeImmutable $to): Collection
    {
        // Query 1 — active roster (stable label + ordering), independent of whether fuel exists.
        $trucks = Truck::query()
            ->where('is_active', true)
            ->orderBy('matricule')
            ->get(['id', 'matricule']);

        // Query 2 — per-truck fuel-recharge aggregates in range, grouped in SQL (no N+1).
        $agg = FuelCardTransaction::query()
            ->where('transaction_type', TransactionType::FUEL_RECHARGE->value)
            ->whereNotNull('truck_id')
            ->whereBetween('occurred_at', [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')])
            ->selectRaw('truck_id')
            ->selectRaw('COUNT(*) AS recharge_count')
            ->selectRaw('COALESCE(SUM(amount_fcfa),0) AS total_spend')
            ->selectRaw('COALESCE(SUM(CASE WHEN kpi_eligible = 1 THEN amount_fcfa ELSE 0 END),0) AS kpi_spend')
            ->selectRaw('COALESCE(SUM(estimated_litres),0) AS est_litres')
            ->selectRaw('MAX(occurred_at) AS last_recharge')
            ->groupBy('truck_id')
            ->get()
            ->keyBy('truck_id');

        return $trucks->map(function (Truck $t) use ($agg): TruckFuelProjection {
            $row = $agg[$t->id] ?? null;

            return new TruckFuelProjection(
                (int) $t->id,
                (string) $t->matricule,
                $row !== null ? (int) $row->recharge_count : 0,
                $row !== null ? (float) $row->total_spend : 0.0,
                $row !== null ? (float) $row->kpi_spend : 0.0,
                $row !== null ? (float) $row->est_litres : 0.0,
                ($row?->last_recharge) !== null ? new DateTimeImmutable((string) $row->last_recharge) : null,
            );
        })->values();
    }

    public function monthlySpend(DateTimeImmutable $from, DateTimeImmutable $to): Collection
    {
        return FuelCardTransaction::query()
            ->where('transaction_type', TransactionType::FUEL_RECHARGE->value)
            ->whereBetween('occurred_at', [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')])
            ->selectRaw("DATE_FORMAT(occurred_at, '%Y-%m') AS month")
            ->selectRaw('COUNT(*) AS recharge_count')
            ->selectRaw('COALESCE(SUM(amount_fcfa),0) AS total_spend')
            ->selectRaw('COALESCE(SUM(CASE WHEN kpi_eligible = 1 THEN amount_fcfa ELSE 0 END),0) AS kpi_spend')
            ->selectRaw('COALESCE(SUM(estimated_litres),0) AS est_litres')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn ($r): MonthlyFuelSpendPoint => new MonthlyFuelSpendPoint(
                (string) $r->month,
                (int) $r->recharge_count,
                (float) $r->total_spend,
                (float) $r->kpi_spend,
                (float) $r->est_litres,
            ));
    }

    public function sourceDistribution(DateTimeImmutable $from, DateTimeImmutable $to): Collection
    {
        return FuelCardTransaction::query()
            ->whereBetween('occurred_at', [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')])
            ->selectRaw('source, transaction_type')
            ->selectRaw('COUNT(*) AS n')
            ->selectRaw('COALESCE(SUM(amount_fcfa),0) AS amount')
            ->groupBy('source', 'transaction_type')
            ->orderBy('source')
            ->orderBy('transaction_type')
            ->get()
            ->map(fn ($r): FuelSourceSlice => new FuelSourceSlice(
                (string) $r->source->value,
                (string) $r->transaction_type->value,
                (int) $r->n,
                (float) $r->amount,
            ));
    }

    public function reviewQueueStats(): FuelReviewQueueStats
    {
        $counts = FuelCardTransaction::query()
            ->selectRaw('review_status, COUNT(*) AS n')
            ->groupBy('review_status')
            ->pluck('n', 'review_status');

        $oldest = FuelCardTransaction::query()
            ->where('review_status', ReviewStatus::PENDING->value)
            ->min('occurred_at');

        return new FuelReviewQueueStats(
            (int) ($counts[ReviewStatus::PENDING->value] ?? 0),
            (int) ($counts[ReviewStatus::RESOLVED->value] ?? 0),
            (int) ($counts[ReviewStatus::NONE->value] ?? 0),
            $oldest !== null ? (string) $oldest : null,
        );
    }

    public function importHistory(int $limit = 50): Collection
    {
        return FuelImportBatch::query()
            ->latest('created_at')
            ->limit($limit)
            ->get(['id', 'original_filename', 'source', 'total_rows', 'accepted_rows', 'rejected_rows', 'created_at'])
            ->map(fn (FuelImportBatch $b): FuelImportBatchProjection => new FuelImportBatchProjection(
                (int) $b->id,
                $b->original_filename !== null ? (string) $b->original_filename : null,
                (string) $b->source,
                (int) $b->total_rows,
                (int) ($b->accepted_rows ?? 0),
                (int) ($b->rejected_rows ?? 0),
                $b->created_at?->format('Y-m-d H:i:s'),
            ));
    }
}
