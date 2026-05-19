<?php

namespace App\Http\Controllers;

use App\Models\Truck;
use App\Models\TruckRestWindow;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TruckRestWindowController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:truck-rest-window-list', ['only' => ['index']]);
        $this->middleware('permission:truck-rest-window-edit', ['only' => ['create', 'store', 'destroy']]);
    }

    public function index(Request $request)
    {
        $from = $request->query('from')
            ? Carbon::parse($request->query('from'))->startOfDay()
            : Carbon::now()->startOfWeek(Carbon::MONDAY);
        $to = $request->query('to')
            ? Carbon::parse($request->query('to'))->endOfDay()
            : $from->copy()->addDays(27)->endOfDay();

        $windows = TruckRestWindow::query()
            ->with(['truck:id,matricule', 'creator:id,name'])
            ->where('end_date', '>=', $from->toDateString())
            ->where('start_date', '<=', $to->toDateString())
            ->orderBy('start_date')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'truck' => $r->truck?->only(['id', 'matricule']),
                'start_date' => $r->start_date->toDateString(),
                'end_date' => $r->end_date->toDateString(),
                'reason' => $r->reason,
                'reason_label' => TruckRestWindow::REASON_LABELS[$r->reason] ?? $r->reason,
                'notes' => $r->notes,
                'creator' => $r->creator?->only(['id', 'name']),
            ]);

        return Inertia::render('logistics/rest-windows/Index', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'windows' => $windows,
            'reasons' => TruckRestWindow::REASON_LABELS,
        ]);
    }

    public function create()
    {
        return Inertia::render('logistics/rest-windows/Create', [
            'trucks' => Truck::query()
                ->where('is_active', true)
                ->orderBy('matricule')
                ->get(['id', 'matricule']),
            'reasons' => TruckRestWindow::REASON_LABELS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'truck_id' => 'required|exists:trucks,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|in:' . implode(',', array_keys(TruckRestWindow::REASON_LABELS)),
            'notes' => 'nullable|string',
        ]);
        $data['created_by'] = auth()->id();

        TruckRestWindow::create($data);

        return redirect()->route('logistics.rest-windows.index')->with('success', 'Fenêtre de repos enregistrée.');
    }

    public function destroy(TruckRestWindow $restWindow)
    {
        $restWindow->delete();
        return redirect()->back()->with('success', 'Fenêtre supprimée.');
    }
}
