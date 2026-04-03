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
        $drivers = Driver::query();

        if ($request->ajax()) {
            return datatables()
                ->of($drivers)
                ->addColumn('actions', function ($driver) {
                    $actions = [
                        [
                            'label' => 'Voir Détails',
                            'href' => route('drivers.show-page', $driver->id),
                            'permission' => true
                        ],
                        [
                            'label' => 'Modifier',
                            'onclick' => 'showModal({
                                title: "Modifier Conducteur - ' . addslashes($driver->name) . '",
                                route: "' . route('drivers.edit', $driver->id) . '",
                                size: "lg"
                            })',
                            'permission' => true
                        ],
                        [
                            'label' => 'Supprimer',
                            'onclick' => 'confirmDelete("' . route('drivers.destroy', $driver->id) . '")',
                            'permission' => true
                        ]
                    ];
                    return view('components.buttons.action', compact('actions'));
                })
                ->rawColumns(['actions'])
                ->make(true);
        }

        $actions = [
            [
                'label' => 'Nouveau Conducteur',
                'onclick' => 'showModal({
                    title: "Nouveau Conducteur",
                    route: "' . route('drivers.create') . '",
                    size: "lg"
                })',
                'permission' => true
            ]
        ];

        return view('pages.drivers.index', compact('actions'));
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
            // Add other validation rules as needed
            'address' => 'nullable|string|max:255',
        ]);

        Driver::firstOrCreate([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            // Add other fields as needed
            'address' => $request->address,
        ]);

        return response([
            'success' => true,
            'message' => 'Conducteur créé avec succès.',
        ]);
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
        return view('pages.drivers.show-page', compact('driver'));
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

        return view('pages.drivers.my-trips', compact('driver', 'trips', 'truck'));
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

        return view('pages.drivers.my-truck', compact('driver', 'truck', 'myTripsCount'));
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

        return view('pages.drivers.checklist-page', compact('driver', 'truck', 'todayChecklist', 'history'));
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
            // Add other validation rules as needed
            'address' => 'nullable|string|max:255',
        ]);

        $driver->update([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            // Add other fields as needed
            'address' => $request->address,
        ]);

        return response([
            'success' => true,
            'message' => 'Conducteur mis à jour avec succès.',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Driver $driver)
    {
        $driver->delete();

        return response([
            'success' => true,
            'message' => 'Conducteur supprimé avec succès.',
        ]);
    }
}
