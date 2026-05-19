<?php

namespace App\Console\Commands;

use App\Models\Auth\User;
use App\Models\LogisticsAlert;
use App\Models\Truck;
use App\Notifications\MaintenanceDueNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

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

        $recipients = User::whereNotNull('email')->get();
        $notifiedCount = 0;

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

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new MaintenanceDueNotification($truck, ['database']));

                try {
                    Notification::send($recipients, new MaintenanceDueNotification($truck, ['mail']));
                    $notifiedCount++;
                } catch (\Throwable $e) {
                    Log::error('MaintenanceDueNotification mail failed', [
                        'truck_id' => $truck->id,
                        'matricule' => $truck->matricule,
                        'error' => $e->getMessage(),
                    ]);
                    $this->warn("Email failed for truck {$truck->matricule}: {$e->getMessage()}");
                }
            }
        }

        $this->info(sprintf(
            'Engine maintenance alerts generated. Notifications sent for %d truck(s) to %d user(s).',
            $notifiedCount,
            $recipients->count()
        ));
        return self::SUCCESS;
    }
}

