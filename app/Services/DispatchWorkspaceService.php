<?php

namespace App\Services;

use App\Models\DailyDispatch;
use App\Models\Driver;
use App\Models\Provider;
use App\Models\Truck;
use App\Models\TruckDriverAssignment;
use Carbon\Carbon;

/**
 * Single data-assembly path for the Dispatch workspace. Reused by the standalone
 * legacy routes (DailyDispatchController, TruckDriverAssignmentController) AND by
 * the Operations Dispatch tab (OperationsController) so there is one source for
 * the daily program and the crew roster — no duplicated query logic.
 *
 * This is a READ/presentation assembler only. It owns no business logic: writes
 * still go through the existing save paths (dispatch store, TruckDriverAssignmentService).
 */
class DispatchWorkspaceService
{
    public function __construct(private readonly RotationAchievementService $achievement) {}

    /** Daily dispatch board props for a date (drivers + their dispatch state). */
    public function programData(Carbon $date): array
    {
        $dispatchedByDriver = DailyDispatch::query()
            ->whereDate('dispatch_date', $date->toDateString())
            ->with(['creator:id,name'])
            ->get()
            ->keyBy('driver_id');

        $dayAchievement = $this->achievement->forDay($date)['by_truck'];
        $trucks = Truck::where('is_active', true)->get(['id', 'matricule'])->keyBy('id');

        $drivers = Driver::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'whatsapp_opt_in_at', 'current_truck_id'])
            ->map(function (Driver $d) use ($dispatchedByDriver, $dayAchievement, $trucks) {
                $dispatch = $dispatchedByDriver->get($d->id);
                $truckId = $dispatch?->truck_id ?? $d->current_truck_id;
                $ach = $truckId ? ($dayAchievement[$truckId] ?? null) : null;
                return [
                    'id' => $d->id,
                    'name' => $d->name,
                    'has_phone' => ! empty($d->phone),
                    'opted_in' => $d->whatsapp_opt_in_at !== null,
                    'dispatched' => $dispatch !== null,
                    'dispatch_id' => $dispatch?->id,
                    'wish_provider_id' => $dispatch?->wish_provider_id,
                    'notified_at' => $dispatch?->notified_at?->format('d/m/Y H:i'),
                    'notification_status' => $dispatch?->notification_status,
                    'notification_error' => $dispatch?->notification_error
                        ? mb_substr($dispatch->notification_error, 0, 120)
                        : null,
                    'current_status' => $dispatch?->current_status,
                    'note' => $dispatch?->notes,
                    'truck' => $truckId ? ($trucks->get($truckId)->matricule ?? null) : null,
                    'done_today' => $ach['done'] ?? 0,
                    'ticket_manquant' => $ach['missing'] ?? false,
                ];
            })
            ->values();

        return [
            'date' => $date->toDateString(),
            'isPast' => $date->isPast() && ! $date->isToday(),
            'isTomorrow' => $date->isTomorrow(),
            'drivers' => $drivers,
            'providers' => Provider::query()->orderBy('name')->get(['id', 'name']),
            'dispatchedCount' => $dispatchedByDriver->count(),
        ];
    }

    /** Crew roster props (trucks with titulaire/assistant + available drivers + history). */
    public function crewData(): array
    {
        $trucks = Truck::query()
            ->where('is_active', true)
            ->with(['activeAssignments.driver:id,name'])
            ->orderBy('matricule')
            ->get(['id', 'matricule'])
            ->map(function (Truck $t) {
                $titulaire = $t->activeAssignments->firstWhere('role', TruckDriverAssignment::ROLE_TITULAIRE);
                $assistant = $t->activeAssignments->firstWhere('role', TruckDriverAssignment::ROLE_ASSISTANT);
                $slot = fn ($a) => $a ? ['assignment_id' => $a->id, 'driver_id' => $a->driver_id, 'name' => $a->driver?->name, 'since' => $a->started_at?->format('d/m/Y')] : null;

                return [
                    'id' => $t->id,
                    'matricule' => $t->matricule,
                    'titulaire' => $slot($titulaire),
                    'assistant' => $slot($assistant),
                    'parking' => $titulaire === null,
                ];
            })
            ->sortByDesc('parking')
            ->values();

        $assignedIds = TruckDriverAssignment::query()->whereNull('ended_at')->pluck('driver_id')->unique()->all();
        $available = Driver::query()
            ->where('is_active', true)
            ->whereNotIn('id', $assignedIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Driver $d) => ['id' => $d->id, 'name' => $d->name])
            ->values();

        $history = TruckDriverAssignment::query()
            ->with(['truck:id,matricule', 'driver:id,name'])
            ->whereNotNull('ended_at')
            ->orderByDesc('ended_at')
            ->limit(50)
            ->get()
            ->map(fn (TruckDriverAssignment $a) => [
                'id' => $a->id,
                'truck' => $a->truck?->matricule,
                'driver' => $a->driver?->name,
                'role' => TruckDriverAssignment::ROLE_LABELS[$a->role] ?? $a->role,
                'started_at' => $a->started_at?->format('d/m/Y'),
                'ended_at' => $a->ended_at?->format('d/m/Y'),
            ]);

        return [
            'trucks' => $trucks,
            'availableDrivers' => $available,
            'history' => $history,
            'roles' => TruckDriverAssignment::ROLE_LABELS,
        ];
    }
}
