<?php

namespace App\Console\Commands;

use App\Models\LogisticsAlert;
use App\Models\Truck;
use Illuminate\Console\Command;

class NotifyDueEngineMaintenance extends Command
{
    protected $signature = 'logistics:notify-due-engine-maintenance';
    protected $description = 'Create alerts for trucks due for 10,000 km engine maintenance';

    public function handle(): int
    {
        $today = now()->toDateString();

        $dueTrucks = Truck::query()
            ->where('is_active', true)
            ->get()
            ->filter(function (Truck $truck) {
                return (float) $truck->total_kilometers >= (float) $truck->nextMaintenanceAtKm();
            });

        foreach ($dueTrucks as $truck) {
            $alreadyExists = LogisticsAlert::query()
                ->where('type', 'due_engine')
                ->where('truck_id', $truck->id)
                ->whereDate('checklist_date', $today)
                ->exists();

            if ($alreadyExists) {
                continue;
            }

            LogisticsAlert::query()->create([
                'type' => 'due_engine',
                'truck_id' => $truck->id,
                'driver_id' => null,
                'checklist_date' => $today,
                'message' => sprintf(
                    'Maintenance moteur due (10,000 km planifie). Truck %s.',
                    $truck->matricule
                ),
            ]);
        }

        $this->info('Engine maintenance alerts generated.');
        return self::SUCCESS;
    }
}

