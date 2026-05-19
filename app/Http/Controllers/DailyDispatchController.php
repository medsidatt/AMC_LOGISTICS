<?php

namespace App\Http\Controllers;

use App\Models\DailyDispatch;
use App\Models\Driver;
use App\Models\Truck;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DailyDispatchController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:daily-dispatch-list', ['only' => ['index']]);
        $this->middleware('permission:daily-dispatch-edit', ['only' => ['store', 'destroy']]);
    }

    public function index(Request $request)
    {
        $date = $request->query('date')
            ? Carbon::parse($request->query('date'))
            : Carbon::tomorrow();

        $dispatchedByDriver = DailyDispatch::query()
            ->whereDate('dispatch_date', $date->toDateString())
            ->with(['truck:id,matricule', 'creator:id,name'])
            ->get()
            ->keyBy('driver_id');

        $drivers = Driver::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (Driver $d) use ($dispatchedByDriver) {
                $dispatch = $dispatchedByDriver->get($d->id);
                return [
                    'id' => $d->id,
                    'name' => $d->name,
                    'dispatched' => $dispatch !== null,
                    'dispatch_id' => $dispatch?->id,
                    'truck_id' => $dispatch?->truck_id,
                    'truck_matricule' => $dispatch?->truck?->matricule,
                    'notes' => $dispatch?->notes,
                    'notified_at' => $dispatch?->notified_at?->format('d/m/Y H:i'),
                ];
            })
            ->values();

        return Inertia::render('logistics/planning/Index', [
            'date' => $date->toDateString(),
            'isPast' => $date->isPast() && !$date->isToday(),
            'isTomorrow' => $date->isTomorrow(),
            'drivers' => $drivers,
            'trucks' => Truck::query()->where('is_active', true)->orderBy('matricule')->get(['id', 'matricule']),
            'dispatchedCount' => $dispatchedByDriver->count(),
        ]);
    }

    /**
     * Upsert dispatch entries for a given date.
     * Payload: { date, dispatches: [ { driver_id, dispatched: bool, truck_id?, notes? } ] }
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => 'required|date',
            'dispatches' => 'required|array',
            'dispatches.*.driver_id' => 'required|exists:drivers,id',
            'dispatches.*.dispatched' => 'required|boolean',
            'dispatches.*.truck_id' => 'nullable|exists:trucks,id',
            'dispatches.*.notes' => 'nullable|string|max:500',
        ]);

        $date = Carbon::parse($data['date'])->toDateString();
        $userId = auth()->id();
        $added = 0;
        $removed = 0;
        $updated = 0;

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

            if ($existing) {
                $existing->update([
                    'truck_id' => $row['truck_id'] ?? null,
                    'notes' => $row['notes'] ?? null,
                ]);
                $updated++;
            } else {
                DailyDispatch::create([
                    'driver_id' => $row['driver_id'],
                    'dispatch_date' => $date,
                    'truck_id' => $row['truck_id'] ?? null,
                    'notes' => $row['notes'] ?? null,
                    'created_by' => $userId,
                ]);
                $added++;
            }
        }

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
}
