<?php

namespace App\Console\Commands;

use App\Models\DailyChecklist;
use App\Models\LogisticsAlert;
use App\Models\TransportTracking;
use App\Models\Truck;
use Illuminate\Console\Command;

class NotifyMissingDailyChecklists extends Command
{
    protected $signature = 'logistics:notify-missing-daily-checklists';
    protected $description = 'Create alerts for missing daily driver checklists (per truck/day)';

    public function handle(): int
    {
        $today = now()->toDateString();

        // Get latest tracking per truck to approximate current assigned driver.
        $latestTrackings = TransportTracking::query()
            ->whereNotNull('truck_id')
            ->whereNotNull('driver_id')
            ->orderByDesc('client_date')
            ->orderByDesc('provider_date')
            ->orderByDesc('id')
            ->get(['truck_id', 'driver_id']);

        $latestByTruck = $latestTrackings->unique('truck_id')->values();

        foreach ($latestByTruck as $row) {
            $truckId = (int) $row->truck_id;
            $driverId = (int) $row->driver_id;

            $truck = Truck::query()->where('id', $truckId)->where('is_active', true)->first();
            if (! $truck) {
                continue;
            }

            $alreadyExists = DailyChecklist::query()
                ->where('truck_id', $truckId)
                ->whereDate('checklist_date', $today)
                ->exists();

            if ($alreadyExists) {
                continue;
            }

            $alertExists = LogisticsAlert::query()
                ->where('type', 'missing_daily')
                ->where('truck_id', $truckId)
                ->whereDate('checklist_date', $today)
                ->exists();

            if ($alertExists) {
                continue;
            }

            LogisticsAlert::query()->create([
                'type' => 'missing_daily',
                'truck_id' => $truckId,
                'driver_id' => $driverId,
                'checklist_date' => $today,
                'message' => sprintf(
                    'Checklist quotidienne manquante pour le camion %s (%s).',
                    $truck->matricule,
                    $today
                ),
            ]);
        }

        $this->info('Missing daily checklist alerts generated.');
        return self::SUCCESS;
    }
}

