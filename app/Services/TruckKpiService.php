<?php

namespace App\Services;

use App\Domain\Operations\Contracts\CapacityCalculatorInterface;
use App\Domain\Operations\Contracts\UtilizationCalculatorInterface;
use App\Domain\Operations\Contracts\WeightCalculatorInterface;
use App\Models\FleetiDailyRecord;
use App\Models\FuelEvent;
use App\Models\FuelTracking;
use App\Models\TransportTracking;
use App\Models\Truck;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TruckKpiService
{
    public function __construct(
        private readonly WeightCalculatorInterface $weightCalculator,
        private readonly CapacityCalculatorInterface $capacityCalculator,
        private readonly UtilizationCalculatorInterface $utilizationCalculator,
    ) {}

    public function compute(Truck $truck, Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $gapThreshold = $this->weightCalculator->gapThreshold();

        $rotations = TransportTracking::query()
            ->where('truck_id', $truck->id)
            ->whereBetween('client_date', [$from, $to])
            ->orderBy('provider_date')
            ->orderBy('id')
            ->get(['id', 'provider_date', 'client_date', 'provider_net_weight', 'client_net_weight', 'gap']);

        $rotationsCount = $rotations->count();
        $tonnageDelivered = (float) $rotations->sum('client_net_weight');
        $tonnageProvider = (float) $rotations->sum('provider_net_weight');
        $gapSum = (float) $rotations->sum('gap');
        $gapViolations = $rotations->filter(fn ($r) => abs((float) ($r->gap ?? 0)) > $gapThreshold)->count();

        $avgCycleDays = $this->averageCycleDays($rotations);

        // Capacity is a single fleet-wide setting, identical for every truck.
        $capacity = max(0.01, $this->capacityCalculator->defaultCapacity());
        $loadRate = $this->utilizationCalculator->loadRate($tonnageDelivered, $capacity, $rotationsCount);

        $fuelLitres = (float) FleetiDailyRecord::query()
            ->where('truck_id', $truck->id)
            ->whereBetween('record_date', [$from->toDateString(), $to->toDateString()])
            ->sum('consumed');

        $fuelPerRotation = $rotationsCount > 0 ? $fuelLitres / $rotationsCount : null;
        $fuelYield = $tonnageDelivered > 0 ? $fuelLitres / $tonnageDelivered : null;

        $anomalies = FuelEvent::query()
            ->where('truck_id', $truck->id)
            ->whereIn('event_type', [FuelEvent::TYPE_DROP, FuelEvent::TYPE_THEFT_SUSPECTED])
            ->whereBetween('detected_at', [$from, $to])
            ->get(['event_type', 'litres_delta']);

        $anomaliesCount = $anomalies->count();
        $anomaliesLitres = (float) $anomalies->sum(fn ($e) => abs((float) ($e->litres_delta ?? 0)));

        $intervalKm = (float) $truck->kmMaintenanceInterval();
        $kmSince = (float) $truck->km_since_maintenance;
        $remainingKm = max(0.0, $intervalKm - $kmSince);
        $maintLevel = 'green';
        if ($remainingKm <= 0) {
            $maintLevel = 'red';
        } elseif ($remainingKm <= ($intervalKm * 0.1)) {
            $maintLevel = 'orange';
        }

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'rotations' => [
                'count' => $rotationsCount,
                'tonnage_delivered' => round($tonnageDelivered, 2),
                'tonnage_provider' => round($tonnageProvider, 2),
            ],
            'cycle' => [
                'avg_days' => $avgCycleDays !== null ? round($avgCycleDays, 2) : null,
            ],
            'weight_gap' => [
                'sum' => round($gapSum, 2),
                'violations' => $gapViolations,
                'threshold' => $gapThreshold,
            ],
            'fuel_anomalies' => [
                'count' => $anomaliesCount,
                'litres' => round($anomaliesLitres, 2),
            ],
            'fuel_per_rotation' => $fuelPerRotation !== null ? round($fuelPerRotation, 2) : null,
            'load_rate' => [
                'rate' => round($loadRate, 4),
                'delivered' => round($tonnageDelivered, 2),
                'theoretical' => round($capacity * $rotationsCount, 2),
                'capacity' => round($capacity, 2),
            ],
            'fuel_yield' => [
                'litres_per_tonne' => $fuelYield !== null ? round($fuelYield, 3) : null,
                'litres' => round($fuelLitres, 2),
                'tonnage' => round($tonnageDelivered, 2),
            ],
            'maintenance' => [
                'interval_km' => round($intervalKm, 0),
                'km_since' => round($kmSince, 0),
                'remaining_km' => round($remainingKm, 0),
                'progress' => $intervalKm > 0 ? round(min(1.0, $kmSince / $intervalKm), 4) : 0,
                'level' => $maintLevel,
            ],
        ];
    }

    private function averageCycleDays($rotations): ?float
    {
        if ($rotations->count() < 2) {
            return null;
        }

        $deltas = [];
        $previous = null;
        foreach ($rotations as $r) {
            $providerDate = $r->provider_date ? Carbon::parse($r->provider_date) : null;
            $clientDate = $r->client_date ? Carbon::parse($r->client_date) : null;
            if (! $providerDate || ! $clientDate) {
                continue;
            }
            if ($previous !== null) {
                $deltas[] = max(0.0, $previous->floatDiffInDays($providerDate));
            }
            $previous = $clientDate;
        }

        if (empty($deltas)) {
            return null;
        }

        return array_sum($deltas) / count($deltas);
    }
}
