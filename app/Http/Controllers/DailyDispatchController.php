<?php

namespace App\Http\Controllers;

use App\Models\DailyDispatch;
use App\Models\Driver;
use App\Models\Provider;
use App\Services\Whatsapp\DispatchNotifier;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DailyDispatchController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:daily-dispatch-list', ['only' => ['index']]);
        $this->middleware('permission:daily-dispatch-edit', ['only' => ['store', 'destroy', 'renotify']]);
    }

    public function index(Request $request)
    {
        $date = $request->query('date')
            ? Carbon::parse($request->query('date'))
            : Carbon::tomorrow();

        $dispatchedByDriver = DailyDispatch::query()
            ->whereDate('dispatch_date', $date->toDateString())
            ->with(['creator:id,name'])
            ->get()
            ->keyBy('driver_id');

        $drivers = Driver::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'whatsapp_opt_in_at'])
            ->map(function (Driver $d) use ($dispatchedByDriver) {
                $dispatch = $dispatchedByDriver->get($d->id);
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
                ];
            })
            ->values();

        return Inertia::render('logistics/planning/Index', [
            'date' => $date->toDateString(),
            'isPast' => $date->isPast() && !$date->isToday(),
            'isTomorrow' => $date->isTomorrow(),
            'drivers' => $drivers,
            'providers' => Provider::query()->orderBy('name')->get(['id', 'name']),
            'dispatchedCount' => $dispatchedByDriver->count(),
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
        ]);

        $date = Carbon::parse($data['date'])->toDateString();
        $userId = auth()->id();
        $added = 0;
        $removed = 0;
        $updated = 0;
        $toNotify = [];

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

            if ($existing) {
                // wish_provider_id may change between saves — it isn't part
                // of the WhatsApp message so we don't re-notify on change.
                $existing->update(['wish_provider_id' => $incomingWish]);
                $updated++;
            } else {
                $created = DailyDispatch::create([
                    'driver_id' => $row['driver_id'],
                    'dispatch_date' => $date,
                    'wish_provider_id' => $incomingWish,
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

        return redirect()
            ->route('logistics.planning.index', ['date' => $date])
            ->with('success', sprintf('Programmation enregistrée : %d ajoutés, %d modifiés, %d retirés.', $added, $updated, $removed));
    }

    public function destroy(DailyDispatch $dispatch)
    {
        $date = $dispatch->dispatch_date->toDateString();
        $dispatch->delete();

        return redirect()
            ->route('logistics.planning.index', ['date' => $date])
            ->with('success', 'Programmation retirée.');
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
