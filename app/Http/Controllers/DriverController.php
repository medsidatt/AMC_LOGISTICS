<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\DailyChecklist;
use App\Models\DailyChecklistIssue;
use App\Models\LogisticsAlert;
use App\Models\TransportTracking;
use App\Models\Truck;
use App\Services\SharePointDailyChecklistService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;


class DriverController extends Controller
{
    public function __construct(
        private readonly SharePointDailyChecklistService $sharePointDailyChecklistService
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
            ->orderBy('name')
            ->paginate(15)
            ->through(fn (Driver $driver) => [
                'id' => $driver->id,
                'name' => $driver->name,
                'email' => $driver->email,
                'phone' => $driver->phone,
                'address' => $driver->address,
                'created_at' => $driver->created_at?->format('d/m/Y'),
            ]);

        return Inertia::render('drivers/Index', [
            'drivers' => $drivers,
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
        ]);

        Driver::firstOrCreate([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
        ]);

        return redirect()->back()->with('success', 'Conducteur créé avec succès.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Driver $driver)
    {
        return $this->showPage($driver);
    }

    public function showPage(Driver $driver)
    {
        return Inertia::render('drivers/Show', [
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
                'email' => $driver->email,
                'phone' => $driver->phone,
                'address' => $driver->address,
                'created_at' => $driver->created_at?->format('d/m/Y'),
                'updated_at' => $driver->updated_at?->format('d/m/Y'),
            ],
        ]);
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
                'provider_date' => $trip->provider_date,
                'client_date' => $trip->client_date,
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
                'total_kilometers' => $truck->total_kilometers,
                'is_active' => $truck->is_active,
                'transporter' => $truck->transporter ? [
                    'id' => $truck->transporter->id,
                    'name' => $truck->transporter->name,
                ] : null,
                'maintenances' => $truck->maintenances->map(fn ($m) => [
                    'id' => $m->id,
                    'maintenance_date' => $m->maintenance_date,
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

        $today = now()->toDateString();

        $todayChecklist = DailyChecklist::query()
            ->where('driver_id', $driver->id)
            ->where('truck_id', $truck->id)
            ->whereDate('checklist_date', $today)
            ->with('issues')
            ->first();

        $history = DailyChecklist::query()
            ->where('driver_id', $driver->id)
            ->where('truck_id', $truck->id)
            ->orderByDesc('checklist_date')
            ->limit(7)
            ->with('issues')
            ->get();

        return Inertia::render('drivers/Checklist', [
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
            ],
            'truck' => [
                'id' => $truck->id,
                'matricule' => $truck->matricule,
                'total_kilometers' => $truck->total_kilometers,
            ],
            'options' => [
                'tire' => DailyChecklist::TIRE_OPTIONS,
                'brake' => DailyChecklist::BRAKE_OPTIONS,
                'light' => DailyChecklist::LIGHT_OPTIONS,
                'oil' => DailyChecklist::OIL_LEVEL_OPTIONS,
                'fuel' => DailyChecklist::FUEL_LEVEL_OPTIONS,
                'general' => DailyChecklist::GENERAL_CONDITION_OPTIONS,
            ],
            'todayChecklist' => $todayChecklist ? [
                'id' => $todayChecklist->id,
                'checklist_date' => $todayChecklist->checklist_date,
                'start_km' => $todayChecklist->start_km,
                'end_km' => $todayChecklist->end_km,
                'fuel_filled' => $todayChecklist->fuel_filled,
                'tire_condition' => $todayChecklist->tire_condition,
                'fuel_level' => $todayChecklist->fuel_level,
                'oil_level' => $todayChecklist->oil_level,
                'brakes' => $todayChecklist->brakes,
                'lights' => $todayChecklist->lights,
                'general_condition_notes' => $todayChecklist->general_condition_notes,
                'notes' => $todayChecklist->notes,
                'issues' => $todayChecklist->issues->map(fn ($i) => [
                    'id' => $i->id,
                    'category' => $i->category,
                    'flagged' => $i->flagged,
                    'issue_notes' => $i->issue_notes,
                ])->toArray(),
            ] : null,
            'history' => $history->map(fn ($c) => [
                'id' => $c->id,
                'checklist_date' => $c->checklist_date,
                'start_km' => $c->start_km,
                'end_km' => $c->end_km,
                'tire_condition' => $c->tire_condition,
                'fuel_level' => $c->fuel_level,
                'oil_level' => $c->oil_level,
                'brakes' => $c->brakes,
                'lights' => $c->lights,
                'general_condition_notes' => $c->general_condition_notes,
                'notes' => $c->notes,
                'issues' => $c->issues->map(fn ($i) => [
                    'id' => $i->id,
                    'category' => $i->category,
                    'flagged' => $i->flagged,
                    'issue_notes' => $i->issue_notes,
                ])->toArray(),
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
        $fuelKeys = implode(',', array_keys(DailyChecklist::FUEL_LEVEL_OPTIONS));
        $generalKeys = implode(',', array_keys(DailyChecklist::GENERAL_CONDITION_OPTIONS));

        $data = $request->validate([
            'checklist_date' => 'required|date',
            'start_km' => 'nullable|numeric|min:0',
            'end_km' => 'nullable|numeric|min:0',
            'fuel_filled' => 'nullable|numeric|min:0',
            'tire_condition' => "required|string|in:{$tireKeys}",
            'fuel_level' => "required|string|in:{$fuelKeys}",
            'fuel_refill' => 'sometimes|boolean',
            'oil_level' => "required|string|in:{$oilKeys}",
            'brakes' => "required|string|in:{$brakeKeys}",
            'lights' => "required|string|in:{$lightKeys}",
            'general_condition_notes' => "required|string|in:{$generalKeys}",
            'notes' => 'nullable|string|max:500',
            'issue_flags' => 'nullable|array',
            'issue_flags.*' => 'string|in:tires,fuel,oil,brakes,lights,general',
            'issue_notes' => 'nullable|array',
            'issue_notes.*' => 'nullable|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            $date = $data['checklist_date'];

            $already = DailyChecklist::query()
                ->where('truck_id', $truck->id)
                ->whereDate('checklist_date', $date)
                ->exists();

            if ($already) {
                throw new \Exception('Une checklist journaliere existe deja pour ce camion a cette date.');
            }

            $dailyChecklist = DailyChecklist::create([
                'truck_id' => $truck->id,
                'driver_id' => $driver->id,
                'checklist_date' => $date,
                'start_km' => $data['start_km'] ?? null,
                'end_km' => $data['end_km'] ?? null,
                'fuel_filled' => $data['fuel_filled'] ?? null,
                'tire_condition' => $data['tire_condition'],
                'fuel_level' => $data['fuel_level'],
                'fuel_refill' => ! empty($data['fuel_refill']),
                'oil_level' => $data['oil_level'],
                'brakes' => $data['brakes'],
                'lights' => $data['lights'],
                'general_condition_notes' => $data['general_condition_notes'],
                'notes' => $data['notes'] ?? null,
            ]);

            $issueFlags = $data['issue_flags'] ?? [];
            $issueNotes = $data['issue_notes'] ?? [];

            foreach ($issueFlags as $category) {
                DailyChecklistIssue::create([
                    'daily_checklist_id' => $dailyChecklist->id,
                    'category' => $category,
                    'flagged' => true,
                    'issue_notes' => $issueNotes[$category] ?? null,
                ]);
            }

            $syncResult = $this->sharePointDailyChecklistService->syncDailyChecklist([
                'Title' => sprintf('Daily checklist %s - %s', $truck->matricule, $date),
                'DriverName' => $driver->name,
                'DriverEmail' => $driver->email,
                'TruckMatricule' => $truck->matricule,
                'ChecklistDate' => $date,
                'TireCondition' => $data['tire_condition'],
                'FuelLevel' => (string) $data['fuel_level'],
                'OilLevel' => (string) $data['oil_level'],
                'Brakes' => $data['brakes'],
                'Lights' => $data['lights'],
                'GeneralConditionNotes' => $data['general_condition_notes'],
                'IssueFlags' => ! empty($issueFlags) ? implode(',', $issueFlags) : '',
                'IssueNotes' => collect($issueFlags)
                    ->map(fn ($c) => $issueNotes[$c] ?? null)
                    ->filter()
                    ->implode(' | '),
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
        ]);

        $driver->update([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
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
