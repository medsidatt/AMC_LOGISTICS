<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesPeriod;
use App\Models\Driver;
use App\Models\DailyChecklist;
use App\Models\DailyChecklistIssue;
use App\Models\LogisticsAlert;
use App\Models\TransportTracking;
use App\Models\Truck;
use App\Services\DriverKpiService;
use App\Services\SharePointDailyChecklistService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;


class DriverController extends Controller
{
    use ResolvesPeriod;

    public function __construct(
        private readonly SharePointDailyChecklistService $sharePointDailyChecklistService,
        private readonly DriverKpiService $kpiService,
    ) {
        $this->middleware('permission:driver-list', ['only' => ['index', 'show', 'showPage']]);
        $this->middleware('permission:driver-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:driver-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:driver-delete', ['only' => ['destroy']]);
    }

    private function resolveLinkedDriver(): ?Driver
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }

        // Primary: direct user_id link
        $driver = Driver::where('user_id', $user->id)->first();
        if ($driver) {
            return $driver;
        }

        // Fallback: match by email then name (for drivers not yet linked)
        $driver = Driver::query()
            ->where(function ($query) use ($user) {
                if (!empty($user->email)) {
                    $query->where('email', $user->email);
                }
                if (!empty($user->name)) {
                    $query->orWhere('name', $user->name);
                }
            })
            ->first();

        // Auto-link if found via fallback
        if ($driver && !$driver->user_id) {
            $driver->update(['user_id' => $user->id]);
        }

        return $driver;
    }

    private function currentUserIsDriver(): bool
    {
        return auth()->check() && auth()->user()->hasRole('Driver');
    }

    private function resolveAssignedTruck(Driver $driver): ?Truck
    {
        $latestTracking = TransportTracking::query()
            ->where('driver_id', $driver->id)
            ->whereNotNull('truck_id')
            ->orderByDesc('client_date')
            ->orderByDesc('provider_date')
            ->orderByDesc('id')
            ->first();

        if (! $latestTracking) {
            return null;
        }

        return Truck::query()
            ->where('id', $latestTracking->truck_id)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $drivers = Driver::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(15)
            ->through(fn (Driver $driver) => [
                'id' => $driver->id,
                'name' => $driver->name,
                'email' => $driver->email,
                'phone' => $driver->phone,
                'address' => $driver->address,
                'is_active' => (bool) $driver->is_active,
                'created_at' => $driver->created_at?->format('d/m/Y'),
            ]);

        $totals = [
            'active' => Driver::query()->where('is_active', true)->count(),
            'total' => Driver::query()->count(),
        ];

        return Inertia::render('drivers/Index', [
            'drivers' => $drivers,
            'totals' => $totals,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('pages.drivers.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:drivers,email',
            'phone' => 'nullable|string|max:15',
            'address' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        Driver::firstOrCreate([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'is_active' => $request->has('is_active') ? (bool) $request->boolean('is_active') : true,
        ]);

        return redirect()->back()->with('success', 'Conducteur créé avec succès.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Driver $driver, Request $request)
    {
        return $this->showPage($driver, $request);
    }

    public function showPage(Driver $driver, Request $request)
    {
        [$from, $to, $preset] = $this->resolvePeriod($request);
        $kpi = $this->kpiService->compute($driver, $from, $to);

        return Inertia::render('drivers/Show', [
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
                'email' => $driver->email,
                'phone' => $driver->phone,
                'address' => $driver->address,
                'is_active' => (bool) $driver->is_active,
                'created_at' => $driver->created_at?->format('d/m/Y'),
                'updated_at' => $driver->updated_at?->format('d/m/Y'),
            ],
            'kpi' => $kpi,
            'filter' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'preset' => $preset,
            ],
        ]);
    }

    public function toggleActive(Driver $driver)
    {
        $driver->update([
            'is_active' => ! $driver->is_active,
        ]);

        $status = $driver->is_active ? 'activé' : 'désactivé';

        return back()->with('success', "Chauffeur {$driver->name} {$status}.");
    }

    /**
     * Driver-only: view own trips filtered to this driver only.
     */
    public function myTrips()
    {
        $driver = $this->resolveLinkedDriver();
        if (!$driver) {
            return redirect()->route('home')
                ->with('error', __('Aucun conducteur lie a ce compte utilisateur.'));
        }

        $trips = TransportTracking::with(['truck', 'provider'])
            ->where('driver_id', $driver->id)
            ->orderByDesc('provider_date')
            ->paginate(20);

        $truck = $this->resolveAssignedTruck($driver);

        return Inertia::render('drivers/MyTrips', [
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
            ],
            'trips' => $trips->through(fn ($trip) => [
                'id' => $trip->id,
                'reference' => $trip->reference,
                'provider_date' => $trip->provider_date?->format('d/m/Y'),
                'client_date' => $trip->client_date?->format('d/m/Y'),
                'provider_net_weight' => $trip->provider_net_weight,
                'client_net_weight' => $trip->client_net_weight,
                'product' => $trip->product,
                'truck' => $trip->truck ? ['id' => $trip->truck->id, 'matricule' => $trip->truck->matricule] : null,
                'provider' => $trip->provider ? ['id' => $trip->provider->id, 'name' => $trip->provider->name] : null,
            ]),
            'truck' => $truck ? [
                'id' => $truck->id,
                'matricule' => $truck->matricule,
            ] : null,
        ]);
    }

    /**
     * Driver-only: view own assigned truck details.
     */
    public function myTruck()
    {
        $driver = $this->resolveLinkedDriver();
        if (!$driver) {
            return redirect()->route('home')
                ->with('error', __('Aucun conducteur lie a ce compte utilisateur.'));
        }

        $truck = $this->resolveAssignedTruck($driver);
        if (!$truck) {
            return redirect()->route('home')
                ->with('error', __('Aucun camion actif assigne a ce conducteur.'));
        }

        $truck->load(['transporter', 'maintenances' => fn($q) => $q->latest('maintenance_date')->take(5)]);

        $myTripsCount = TransportTracking::where('driver_id', $driver->id)
            ->where('truck_id', $truck->id)
            ->count();

        return Inertia::render('drivers/MyTruck', [
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
            ],
            'truck' => [
                'id' => $truck->id,
                'matricule' => $truck->matricule,
                'total_kilometers' => (float) ($truck->total_kilometers ?? 0),
                'is_active' => $truck->is_active,
                'transporter' => $truck->transporter ? [
                    'id' => $truck->transporter->id,
                    'name' => $truck->transporter->name,
                ] : null,
                'fuel_level' => $truck->fleeti_last_fuel_level !== null ? (float) $truck->fleeti_last_fuel_level : null,
                'speed' => $truck->fleeti_last_speed_kmh !== null ? (float) $truck->fleeti_last_speed_kmh : null,
                'movement_status' => $truck->fleeti_last_movement_status,
                'latitude' => $truck->fleeti_last_latitude !== null ? (float) $truck->fleeti_last_latitude : null,
                'longitude' => $truck->fleeti_last_longitude !== null ? (float) $truck->fleeti_last_longitude : null,
                'last_sync' => $truck->fleeti_last_synced_at?->format('d/m/Y H:i'),
                'maintenance_level' => $truck->maintenanceLevelByType(),
                'maintenances' => $truck->maintenances->map(fn ($m) => [
                    'id' => $m->id,
                    'maintenance_date' => $m->maintenance_date instanceof \Carbon\Carbon
                        ? $m->maintenance_date->format('d/m/Y')
                        : $m->maintenance_date,
                    'type' => $m->type,
                    'description' => $m->description,
                    'cost' => $m->cost,
                ])->values()->toArray(),
            ],
            'myTripsCount' => $myTripsCount,
        ]);
    }

    public function checklistPage()
    {
        if (! $this->currentUserIsDriver()) {
            abort(403, 'Access denied. Driver role is required.');
        }

        $driver = $this->resolveLinkedDriver();
        if (! $driver) {
            return redirect()
                ->back()
                ->with('error', 'Aucun conducteur lie a ce compte utilisateur.');
        }

        $truck = $this->resolveAssignedTruck($driver);
        if (! $truck) {
            return redirect()
                ->back()
                ->with('error', 'Aucun camion actif assigne a ce conducteur.');
        }

        $weekStart = DailyChecklist::weekStartFor(now())->toDateString();

        $currentChecklist = DailyChecklist::query()
            ->where('driver_id', $driver->id)
            ->where('truck_id', $truck->id)
            ->whereDate('week_start_date', $weekStart)
            ->first();

        $history = DailyChecklist::query()
            ->where('driver_id', $driver->id)
            ->where('truck_id', $truck->id)
            ->orderByDesc('week_start_date')
            ->limit(8)
            ->get();

        return Inertia::render('drivers/Checklist', [
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
            ],
            'truck' => [
                'id' => $truck->id,
                'matricule' => $truck->matricule,
                'tire_count' => (int) ($truck->tire_count ?? 26),
                'total_kilometers' => (float) ($truck->total_kilometers ?? 0),
                'fleeti_last_kilometers' => $truck->fleeti_last_kilometers !== null ? (float) $truck->fleeti_last_kilometers : null,
                'fleeti_last_fuel_level' => $truck->fleeti_last_fuel_level !== null ? (float) $truck->fleeti_last_fuel_level : null,
                'fleeti_last_synced_at' => $truck->fleeti_last_synced_at?->format('d/m/Y H:i'),
                'fleeti_last_speed_kmh' => $truck->fleeti_last_speed_kmh !== null ? (float) $truck->fleeti_last_speed_kmh : null,
                'fleeti_last_movement_status' => $truck->fleeti_last_movement_status,
            ],
            'options' => [
                'tire' => DailyChecklist::TIRE_OPTIONS,
                'brake' => DailyChecklist::BRAKE_OPTIONS,
                'light' => DailyChecklist::LIGHT_OPTIONS,
                'oil' => DailyChecklist::OIL_LEVEL_OPTIONS,
                'general' => DailyChecklist::GENERAL_CONDITION_OPTIONS,
            ],
            'currentWeekStart' => $weekStart,
            'currentChecklist' => $currentChecklist ? [
                'id' => $currentChecklist->id,
                'checklist_date' => $currentChecklist->checklist_date instanceof \Carbon\Carbon
                    ? $currentChecklist->checklist_date->format('d/m/Y')
                    : $currentChecklist->checklist_date,
                'week_start_date' => $currentChecklist->week_start_date instanceof \Carbon\Carbon
                    ? $currentChecklist->week_start_date->format('d/m/Y')
                    : $currentChecklist->week_start_date,
                'status' => $currentChecklist->status,
                'tire_condition' => $currentChecklist->tire_condition,
                'oil_level' => $currentChecklist->oil_level,
                'brakes' => $currentChecklist->brakes,
                'lights' => $currentChecklist->lights,
                'general_condition_notes' => $currentChecklist->general_condition_notes,
                'notes' => $currentChecklist->notes,
            ] : null,
            'history' => $history->map(fn ($c) => [
                'id' => $c->id,
                'checklist_date' => $c->checklist_date instanceof \Carbon\Carbon
                    ? $c->checklist_date->format('d/m/Y')
                    : $c->checklist_date,
                'week_start_date' => $c->week_start_date instanceof \Carbon\Carbon
                    ? $c->week_start_date->format('d/m/Y')
                    : $c->week_start_date,
                'status' => $c->status,
                'tire_condition' => $c->tire_condition,
                'oil_level' => $c->oil_level,
                'brakes' => $c->brakes,
                'lights' => $c->lights,
                'general_condition_notes' => $c->general_condition_notes,
                'notes' => $c->notes,
            ])->toArray(),
        ]);
    }

    public function submitChecklist(Request $request)
    {
        if (! $this->currentUserIsDriver()) {
            abort(403, 'Access denied. Driver role is required.');
        }

        $driver = $this->resolveLinkedDriver();
        if (! $driver) {
            return redirect()->back()->withInput()
                ->with('error', 'Aucun conducteur lie a ce compte utilisateur.');
        }

        $truck = $this->resolveAssignedTruck($driver);
        if (! $truck) {
            return redirect()->back()->withInput()
                ->with('error', 'Aucun camion actif assigne a ce conducteur.');
        }

        $tireKeys = implode(',', array_keys(DailyChecklist::TIRE_OPTIONS));
        $brakeKeys = implode(',', array_keys(DailyChecklist::BRAKE_OPTIONS));
        $lightKeys = implode(',', array_keys(DailyChecklist::LIGHT_OPTIONS));
        $oilKeys = implode(',', array_keys(DailyChecklist::OIL_LEVEL_OPTIONS));
        $generalKeys = implode(',', array_keys(DailyChecklist::GENERAL_CONDITION_OPTIONS));

        $data = $request->validate([
            'checklist_date' => 'required|date',
            'tire_condition' => "required|string|in:{$tireKeys}",
            'oil_level' => "required|string|in:{$oilKeys}",
            'brakes' => "required|string|in:{$brakeKeys}",
            'lights' => "required|string|in:{$lightKeys}",
            'general_condition_notes' => "required|string|in:{$generalKeys}",
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            $date = $data['checklist_date'];
            $weekStart = DailyChecklist::weekStartFor(\Carbon\Carbon::parse($date))->toDateString();

            $already = DailyChecklist::query()
                ->where('truck_id', $truck->id)
                ->whereDate('week_start_date', $weekStart)
                ->exists();

            if ($already) {
                throw new \Exception('Une checklist hebdomadaire existe deja pour ce camion cette semaine.');
            }

            $dailyChecklist = DailyChecklist::create([
                'truck_id' => $truck->id,
                'driver_id' => $driver->id,
                'checklist_date' => $date,
                'week_start_date' => $weekStart,
                'status' => DailyChecklist::STATUS_PENDING,
                'tire_condition' => $data['tire_condition'],
                'oil_level' => $data['oil_level'],
                'brakes' => $data['brakes'],
                'lights' => $data['lights'],
                'general_condition_notes' => $data['general_condition_notes'],
                'notes' => $data['notes'] ?? null,
            ]);

            $syncResult = $this->sharePointDailyChecklistService->syncDailyChecklist([
                'Title' => sprintf('Weekly checklist %s - week of %s', $truck->matricule, $weekStart),
                'DriverName' => $driver->name,
                'DriverEmail' => $driver->email,
                'TruckMatricule' => $truck->matricule,
                'ChecklistDate' => $date,
                'TireCondition' => $data['tire_condition'],
                'OilLevel' => (string) $data['oil_level'],
                'Brakes' => $data['brakes'],
                'Lights' => $data['lights'],
                'GeneralConditionNotes' => $data['general_condition_notes'],
                'IssueFlags' => '',
                'IssueNotes' => '',
//                'SharepointLocalChecklistId' => (string) $dailyChecklist->id,
            ]);

            if (empty($syncResult['success']) || empty($syncResult['sharepoint_item_id'])) {
                throw new \Exception('SharePoint sync failed.');
            }

            $dailyChecklist->update([
                'sharepoint_item_id' => $syncResult['sharepoint_item_id']
            ]);

            DB::commit();

            return redirect()
                ->route('drivers.checklist-page')
                ->with('success', 'Checklist soumise avec succes et synchronisee avec SharePoint.');

        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    public function issuesPage()
    {
        if (! $this->currentUserIsDriver()) {
            abort(403, 'Access denied. Driver role is required.');
        }

        $driver = $this->resolveLinkedDriver();
        if (! $driver) {
            return redirect()->back()->with('error', 'Aucun conducteur lie a ce compte utilisateur.');
        }

        $truck = $this->resolveAssignedTruck($driver);
        if (! $truck) {
            return redirect()->back()->with('error', 'Aucun camion actif assigne a ce conducteur.');
        }

        $recent = DailyChecklistIssue::query()
            ->where('truck_id', $truck->id)
            ->orderByDesc('reported_at')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        return Inertia::render('drivers/Issues', [
            'driver' => ['id' => $driver->id, 'name' => $driver->name],
            'truck' => [
                'id' => $truck->id,
                'matricule' => $truck->matricule,
                'tire_count' => (int) ($truck->tire_count ?? 26),
            ],
            'options' => [
                'severity' => DailyChecklistIssue::SEVERITY_OPTIONS,
                'light_positions' => DailyChecklist::LIGHT_POSITION_OPTIONS,
            ],
            'recent' => $recent->map(fn ($i) => [
                'id' => $i->id,
                'category' => $i->category,
                'severity' => $i->severity,
                'issue_notes' => $i->issue_notes,
                'positions' => $i->positions ? explode(',', $i->positions) : [],
                'reported_at' => $i->reported_at?->format('d/m/Y H:i'),
                'resolved_at' => $i->resolved_at?->format('d/m/Y H:i'),
                'resolution_notes' => $i->resolution_notes,
            ])->values()->toArray(),
        ]);
    }

    public function reportIssue(Request $request)
    {
        if (! $this->currentUserIsDriver()) {
            abort(403, 'Access denied. Driver role is required.');
        }

        $driver = $this->resolveLinkedDriver();
        if (! $driver) {
            return redirect()->back()->withInput()->with('error', 'Aucun conducteur lie a ce compte utilisateur.');
        }

        $truck = $this->resolveAssignedTruck($driver);
        if (! $truck) {
            return redirect()->back()->withInput()->with('error', 'Aucun camion actif assigne a ce conducteur.');
        }

        $allowedCategories = ['tires', 'brakes', 'lights', 'oil', 'fuel', 'general'];
        $severityKeys = array_keys(DailyChecklistIssue::SEVERITY_OPTIONS);
        $lightPositionKeys = array_keys(DailyChecklist::LIGHT_POSITION_OPTIONS);
        $tireCount = (int) ($truck->tire_count ?? 26);

        $data = $request->validate([
            'flagged' => 'required|array|min:1',
            'flagged.*' => ['string', 'in:' . implode(',', $allowedCategories)],
            'severity' => 'nullable|array',
            'severity.*' => ['nullable', 'string', 'in:' . implode(',', $severityKeys)],
            'notes' => 'nullable|array',
            'notes.*' => 'nullable|string|max:500',
            'positions' => 'nullable|array',
            'positions.tires' => 'nullable|array',
            'positions.tires.*' => ['string', function ($attr, $value, $fail) use ($tireCount) {
                if (! is_string($value) || ! preg_match('/^tire_(\d+)$/', $value, $m)) {
                    $fail('Position de pneu invalide.');
                    return;
                }
                $n = (int) $m[1];
                if ($n < 1 || $n > $tireCount) {
                    $fail("Position de pneu hors limites (1-{$tireCount}).");
                }
            }],
            'positions.lights' => 'nullable|array',
            'positions.lights.*' => ['string', 'in:' . implode(',', $lightPositionKeys)],
        ]);

        $severityMap = $data['severity'] ?? [];
        $notesMap = $data['notes'] ?? [];
        $positionsMap = $data['positions'] ?? [];

        try {
            DB::beginTransaction();
            foreach ($data['flagged'] as $category) {
                $positions = $positionsMap[$category] ?? [];
                DailyChecklistIssue::create([
                    'truck_id' => $truck->id,
                    'driver_id' => $driver->id,
                    'category' => $category,
                    'flagged' => true,
                    'severity' => $severityMap[$category] ?? null,
                    'issue_notes' => $notesMap[$category] ?? null,
                    'positions' => ! empty($positions) ? implode(',', $positions) : null,
                    'reported_at' => now(),
                ]);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->with('error', 'Échec de l\'enregistrement: ' . $e->getMessage());
        }

        $count = count($data['flagged']);
        $msg = $count === 1
            ? '1 problème signalé.'
            : "{$count} problèmes signalés.";
        return redirect()->route('drivers.issues')->with('success', $msg);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Driver $driver)
    {
        return view('pages.drivers.edit', compact('driver'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Driver $driver)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:drivers,email,' . $driver->id,
            'phone' => 'nullable|string|max:15',
            'address' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $driver->update([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'is_active' => $request->has('is_active') ? (bool) $request->boolean('is_active') : (bool) $driver->is_active,
        ]);

        return redirect()->back()->with('success', 'Conducteur mis à jour avec succès.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Driver $driver)
    {
        $driver->delete();

        return redirect()->back()->with('success', 'Conducteur supprimé avec succès.');
    }
}
