<?php

namespace App\Http\Controllers;

use App\Models\ClientDemandPlan;
use App\Models\Project;
use App\Models\Provider;
use App\Services\ObjectiveHistoryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClientDemandController extends Controller
{
    public function __construct(private readonly ObjectiveHistoryService $objectiveHistory)
    {
        $this->middleware('permission:client-demand-list', ['only' => ['index']]);
        $this->middleware('permission:client-demand-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:client-demand-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:client-demand-delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        $weekStart = $request->query('week')
            ? Carbon::parse($request->query('week'))->startOfWeek(Carbon::MONDAY)
            : null;

        $query = ClientDemandPlan::query()
            ->with(['project:id,name,code', 'provider:id,name', 'creator:id,name'])
            ->orderByDesc('week_start_date')
            ->orderBy('priority');

        if ($weekStart) {
            $query->where('week_start_date', $weekStart->toDateString());
        }

        $demands = $query->get()->map(fn ($d) => [
            'id' => $d->id,
            'week_start_date' => $d->week_start_date->toDateString(),
            'project' => $d->project?->only(['id', 'name', 'code']),
            'provider' => $d->provider?->only(['id', 'name']),
            'client_name' => $d->client_name,
            'required_tons' => (float) $d->required_tons,
            'required_trucks' => $d->required_trucks,
            'product' => $d->product,
            'priority' => $d->priority,
            'priority_label' => ClientDemandPlan::PRIORITY_LABELS[$d->priority] ?? '',
            'allocated_tons' => (float) $d->allocated_tons,
            'coverage_rate' => $d->coverage_rate,
            'creator' => $d->creator?->only(['id', 'name']),
            'notes' => $d->notes,
        ]);

        return Inertia::render('logistics/demands/Index', [
            'demands' => $demands,
            'weekFilter' => $weekStart?->toDateString(),
        ]);
    }

    public function create()
    {
        return Inertia::render('logistics/demands/Create', [
            'projects' => Project::query()->orderBy('name')->get(['id', 'name', 'code']),
            'providers' => Provider::query()->orderBy('name')->get(['id', 'name']),
            'products' => ClientDemandPlan::PRODUCTS,
            'priorities' => ClientDemandPlan::PRIORITY_LABELS,
            'defaultWeekStart' => Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateDemand($request, requireNote: true);
        $note = $data['change_note'];
        unset($data['change_note']);

        $data['week_start_date'] = Carbon::parse($data['week_start_date'])->startOfWeek(Carbon::MONDAY)->toDateString();
        $data['created_by'] = auth()->id();

        $demand = ClientDemandPlan::create($data);

        $this->objectiveHistory->record(
            subject: $demand,
            subjectLabel: $this->demandLabel($demand),
            fieldName: 'required_tons',
            fieldLabel: 'Tonnage demandé (t)',
            oldValue: null,
            newValue: $demand->required_tons,
            note: $note,
            context: ['scope' => 'demand_create', 'week_start_date' => $demand->week_start_date->toDateString()],
        );
        if ($demand->required_trucks !== null) {
            $this->objectiveHistory->record(
                subject: $demand,
                subjectLabel: $this->demandLabel($demand),
                fieldName: 'required_trucks',
                fieldLabel: 'Camions demandés',
                oldValue: null,
                newValue: $demand->required_trucks,
                note: $note,
                context: ['scope' => 'demand_create', 'week_start_date' => $demand->week_start_date->toDateString()],
            );
        }

        return redirect()
            ->route('logistics.demands.index', ['week' => $data['week_start_date']])
            ->with('success', 'Demande client enregistrée.');
    }

    public function edit(ClientDemandPlan $demand)
    {
        return Inertia::render('logistics/demands/Create', [
            'demand' => [
                'id' => $demand->id,
                'week_start_date' => $demand->week_start_date->toDateString(),
                'project_id' => $demand->project_id,
                'provider_id' => $demand->provider_id,
                'client_name' => $demand->client_name,
                'required_tons' => (float) $demand->required_tons,
                'required_trucks' => $demand->required_trucks,
                'product' => $demand->product,
                'priority' => $demand->priority,
                'notes' => $demand->notes,
            ],
            'projects' => Project::query()->orderBy('name')->get(['id', 'name', 'code']),
            'providers' => Provider::query()->orderBy('name')->get(['id', 'name']),
            'products' => ClientDemandPlan::PRODUCTS,
            'priorities' => ClientDemandPlan::PRIORITY_LABELS,
            'defaultWeekStart' => $demand->week_start_date->toDateString(),
        ]);
    }

    public function update(Request $request, ClientDemandPlan $demand)
    {
        $oldTons = (float) $demand->required_tons;
        $oldTrucks = $demand->required_trucks;

        $newTons = (float) $request->input('required_tons', $oldTons);
        $newTrucks = $request->filled('required_trucks') ? (int) $request->input('required_trucks') : null;

        $objectiveChanged = abs($newTons - $oldTons) > 0.0001
            || (string) ($newTrucks ?? '') !== (string) ($oldTrucks ?? '');

        $data = $this->validateDemand($request, requireNote: $objectiveChanged);
        $note = $data['change_note'] ?? null;
        unset($data['change_note']);

        $data['week_start_date'] = Carbon::parse($data['week_start_date'])->startOfWeek(Carbon::MONDAY)->toDateString();
        $demand->update($data);

        if ($note) {
            $this->objectiveHistory->record(
                subject: $demand,
                subjectLabel: $this->demandLabel($demand),
                fieldName: 'required_tons',
                fieldLabel: 'Tonnage demandé (t)',
                oldValue: $oldTons,
                newValue: $demand->required_tons,
                note: $note,
                context: ['scope' => 'demand_update', 'week_start_date' => $demand->week_start_date->toDateString()],
            );
            $this->objectiveHistory->record(
                subject: $demand,
                subjectLabel: $this->demandLabel($demand),
                fieldName: 'required_trucks',
                fieldLabel: 'Camions demandés',
                oldValue: $oldTrucks,
                newValue: $demand->required_trucks,
                note: $note,
                context: ['scope' => 'demand_update', 'week_start_date' => $demand->week_start_date->toDateString()],
            );
        }

        return redirect()
            ->route('logistics.demands.index', ['week' => $data['week_start_date']])
            ->with('success', 'Demande client mise à jour.');
    }

    public function destroy(ClientDemandPlan $demand)
    {
        $demand->delete();
        return redirect()->route('logistics.demands.index')->with('success', 'Demande supprimée.');
    }

    private function demandLabel(ClientDemandPlan $demand): string
    {
        $who = $demand->client_name
            ?: ($demand->project?->name ?: 'Demande');
        return 'Demande ' . $who . ' — semaine du ' . $demand->week_start_date->toDateString();
    }

    private function validateDemand(Request $request, bool $requireNote = false): array
    {
        $rules = [
            'week_start_date' => 'required|date',
            'project_id' => 'nullable|exists:projects,id',
            'provider_id' => 'nullable|exists:providers,id',
            'client_name' => 'nullable|string|max:255',
            'required_tons' => 'required|numeric|min:0',
            'required_trucks' => 'nullable|integer|min:0',
            'product' => 'nullable|in:0/3,3/8,8/16',
            'priority' => 'required|integer|min:1|max:5',
            'notes' => 'nullable|string',
        ];
        if ($requireNote) {
            $rules['change_note'] = 'required|string|min:5|max:1000';
        }
        return $request->validate($rules);
    }
}
