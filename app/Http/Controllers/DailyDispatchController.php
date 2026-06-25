<?php

namespace App\Http\Controllers;

use App\Models\DailyDispatch;
use App\Models\Driver;
use App\Models\FleetObjective;
use App\Models\Provider;
use App\Models\Truck;
use App\Services\PlanningPeriodResolver;
use App\Services\RotationAchievementService;
use App\Services\Whatsapp\DispatchNotifier;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DailyDispatchController extends Controller
{
    public function __construct(
        private readonly RotationAchievementService $achievement,
    ) {
        $this->middleware('permission:daily-dispatch-list', ['only' => ['weekly']]);
        $this->middleware('permission:daily-dispatch-edit', ['only' => ['store', 'destroy', 'renotify']]);
    }

    /**
     * Planning scoreboard: planned vs done per truck + the fleet roll-up, for any
     * period (Week / Month / Year / Custom). Targets resolve hierarchically via
     * the view mode; the realized side is computed from trips in the range.
     */
    public function weekly(Request $request, PlanningPeriodResolver $periods)
    {
        $p = $periods->resolve(
            $request->query('mode', FleetObjective::PERIOD_WEEK),
            $request->query('anchor') ?? $request->query('start'),
            $request->query('start'),
            $request->query('end'),
        );

        return Inertia::render('logistics/planning/Weekly', [
            'mode' => $p['mode'],
            'period' => ['start' => $p['start']->toDateString(), 'end' => $p['end']->toDateString()],
            'achievement' => $this->achievement->forPeriod($p['start'], $p['end'], $p['mode']),
        ]);
    }

    /**
     * Upsert dispatch entries for a given date.
     * Payload: { date, dispatches: [ { driver_id, dispatched: bool, truck_id?, notes? } ] }
     */
    public function store(Request $request, DispatchNotifier $notifier)
    {
        $data = $request->validate([
            'date' => 'required|date',
            'dispatches' => 'required|array',
            'dispatches.*.driver_id' => 'required|exists:drivers,id',
            'dispatches.*.dispatched' => 'required|boolean',
            'dispatches.*.wish_provider_id' => 'nullable|exists:providers,id',
            'dispatches.*.note' => 'nullable|string|max:200',
        ]);

        $date = Carbon::parse($data['date'])->toDateString();
        $userId = auth()->id();
        $added = 0;
        $removed = 0;
        $updated = 0;
        $toNotify = [];

        // Each driver is linked to a truck — record it on the dispatch so the
        // plan (and the copyable program message) is expressed in trucks.
        $driverTrucks = Driver::whereIn('id', collect($data['dispatches'])->pluck('driver_id'))
            ->pluck('current_truck_id', 'id');

        // Don't notify on past dates — those rows are read-only in the UI but
        // a stale POST could still bypass that.
        $isFutureOrToday = Carbon::parse($date)->gte(Carbon::today());

        foreach ($data['dispatches'] as $row) {
            $existing = DailyDispatch::where('driver_id', $row['driver_id'])
                ->whereDate('dispatch_date', $date)
                ->first();

            if (! $row['dispatched']) {
                if ($existing) {
                    $existing->delete();
                    $removed++;
                }
                continue;
            }

            $incomingWish = $row['wish_provider_id'] ?? null;
            $note = $row['note'] ?? null;
            $truckId = $driverTrucks[$row['driver_id']] ?? null;

            if ($existing) {
                // wish_provider_id / note / truck may change between saves — none
                // are part of the WhatsApp message, so we don't re-notify.
                $existing->update(['wish_provider_id' => $incomingWish, 'notes' => $note, 'truck_id' => $truckId]);
                $updated++;
            } else {
                $created = DailyDispatch::create([
                    'driver_id' => $row['driver_id'],
                    'dispatch_date' => $date,
                    'wish_provider_id' => $incomingWish,
                    'truck_id' => $truckId,
                    'notes' => $note,
                    'created_by' => $userId,
                    'notification_status' => DailyDispatch::STATUS_PENDING,
                ]);
                $added++;

                if ($isFutureOrToday) {
                    $toNotify[] = $created->id;
                }
            }
        }

        $notifier->notifyForDispatchIds($toNotify);

        // back() so the same save path serves both the standalone route and the
        // Operations Dispatch tab — returns to whichever surface posted.
        return back()->with('success', sprintf('Programmation enregistrée : %d ajoutés, %d modifiés, %d retirés.', $added, $updated, $removed));
    }

    public function destroy(DailyDispatch $dispatch)
    {
        $dispatch->delete();

        return back()->with('success', 'Programmation retirée.');
    }

    /**
     * Re-queue the WhatsApp notification for a single dispatch (used by the
     * "Renvoyer" button on failed / no-phone rows after the issue is fixed).
     */
    public function renotify(DailyDispatch $dispatch, DispatchNotifier $notifier)
    {
        $date = Carbon::parse($dispatch->dispatch_date);
        if ($date->lt(Carbon::today())) {
            return back()->with('error', "Impossible de renotifier une date passée.");
        }

        $notifier->notifyOne($dispatch);

        return back()->with('success', 'Notification relancée.');
    }
}
