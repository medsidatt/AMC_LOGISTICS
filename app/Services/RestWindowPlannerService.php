<?php

namespace App\Services;

use App\Models\Maintenance;
use App\Models\Truck;
use App\Models\TruckRestWindow;
use Carbon\Carbon;

class RestWindowPlannerService
{
    public const ROTATION_WARNING = 11;
    public const KM_WARNING = 8500;

    public function proposeForSurplus(array $surplusByTruck, ?int $userId = null): array
    {
        $proposed = [];

        foreach ($surplusByTruck as $truckId => $freeDays) {
            if (empty($freeDays)) {
                continue;
            }

            sort($freeDays);
            $truck = Truck::find($truckId);
            if (! $truck) {
                continue;
            }

            $reason = $this->resolveReason($truck);
            $ranges = $this->collapseToRanges($freeDays);

            foreach ($ranges as [$start, $end]) {
                $proposed[] = [
                    'truck_id' => $truckId,
                    'start_date' => $start,
                    'end_date' => $end,
                    'reason' => $reason,
                    'maintenance_id' => null,
                    'notes' => 'Proposé automatiquement par l\'optimiseur (jour libre).',
                    'created_by' => $userId,
                ];
            }
        }

        return $proposed;
    }

    public function resolveReason(Truck $truck): string
    {
        $rotationsSince = (int) $truck->rotations_since_maintenance;
        if ($rotationsSince >= self::ROTATION_WARNING) {
            return TruckRestWindow::REASON_TIRE_CHANGE;
        }

        $kmSince = (float) $truck->km_since_maintenance;
        if ($kmSince >= self::KM_WARNING) {
            return TruckRestWindow::REASON_OIL_CHANGE;
        }

        return TruckRestWindow::REASON_SURPLUS_CAPACITY;
    }

    private function collapseToRanges(array $sortedDays): array
    {
        $ranges = [];
        $rangeStart = null;
        $rangePrev = null;

        foreach ($sortedDays as $dayStr) {
            $day = Carbon::parse($dayStr);
            if ($rangeStart === null) {
                $rangeStart = $dayStr;
                $rangePrev = $day;
                continue;
            }
            $expectedNext = $rangePrev->copy()->addDay();
            if ($day->toDateString() === $expectedNext->toDateString()) {
                $rangePrev = $day;
                continue;
            }
            $ranges[] = [$rangeStart, $rangePrev->toDateString()];
            $rangeStart = $dayStr;
            $rangePrev = $day;
        }
        if ($rangeStart !== null) {
            $ranges[] = [$rangeStart, $rangePrev->toDateString()];
        }

        return $ranges;
    }
}
