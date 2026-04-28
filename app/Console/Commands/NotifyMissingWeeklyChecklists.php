<?php

namespace App\Console\Commands;

use App\Models\DailyChecklist;
use App\Models\LogisticsAlert;
use App\Models\TransportTracking;
use App\Models\Truck;
use Illuminate\Console\Command;

class NotifyMissingWeeklyChecklists extends Command
{
    protected $signature = 'logistics:notify-missing-weekly-checklists';
    protected $description = 'Create alerts for missing weekly driver checklists (per truck/ISO week)';

    public function handle(): int
    {
        $weekStart = DailyChecklist::weekStartFor(now())->toDateString();

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
                ->whereDate('week_start_date', $weekStart)
                ->exists();

            if ($alreadyExists) {
                continue;
            }

            $alertExists = LogisticsAlert::query()
                ->where('type', 'missing_weekly')
                ->where('truck_id', $truckId)
                ->whereDate('checklist_date', $weekStart)
                ->exists();

            if ($alertExists) {
                continue;
            }

            LogisticsAlert::query()->create([
                'type' => 'missing_weekly',
                'truck_id' => $truckId,
                'driver_id' => $driverId,
                'checklist_date' => $weekStart,
                'message' => sprintf(
                    'Checklist hebdomadaire manquante pour le camion %s (semaine du %s).',
                    $truck->matricule,
                    $weekStart
                ),
            ]);
        }

        $this->info('Missing weekly checklist alerts generated.');
        return self::SUCCESS;
    }
}
