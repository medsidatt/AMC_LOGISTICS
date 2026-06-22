<?php

namespace App\Http\Controllers;

use App\Models\FleetObjective;
use App\Services\FleetObjectiveService;
use App\Services\PlanningPeriodResolver;
use App\Services\RotationAchievementService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * CRUD for multi-level planning objectives (WEEK / MONTH / YEAR / CUSTOM). The
 * scoreboard reads these through hierarchical resolution; this controller is the
 * manager-facing authoring surface (create / edit / archive / history).
 */
class FleetObjectiveController extends Controller
{
    public function __construct(
        private readonly FleetObjectiveService $objectives,
        private readonly RotationAchievementService $achievement,
        private readonly PlanningPeriodResolver $periods,
    ) {
        $this->middleware('permission:fleet-roster-plan');
    }

    public function index(Request $request)
    {
        $showArchived = $request->boolean('archived');

        $objectives = FleetObjective::with('creator:id,name')
            ->when(! $showArchived, fn ($q) => $q->active())
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->limit(60)
            ->get();

        $rows = $objectives->map(function (FleetObjective $o) {
            // Achievement for the objective's own period, via hierarchical resolution.
            $a = $this->achievement->forPeriod(
                $o->start_date->copy(),
                $o->end_date->copy()->endOfDay(),
                $o->period_type,
            )['fleet'];

            return [
                'id' => $o->id,
                'period_type' => $o->period_type,
                'start_date' => $o->start_date->toDateString(),
                'end_date' => $o->end_date->toDateString(),
                'target_tons' => (float) $o->target_tons,
                'target_rotations' => (int) $o->target_rotations,
                'achieved_tons' => $a['done_tons'],
                'achieved_rotations' => $a['done_rotations'],
                'remaining_tons' => $a['remaining_tons'],
                'remaining_rotations' => $a['remaining_rotations'],
                'pct' => $a['pct'],
                'working_trucks' => $o->working_trucks,
                'notes' => $o->notes,
                'archived' => $o->archived_at !== null,
                'created_by' => $o->creator?->name,
            ];
        })->values();

        return Inertia::render('logistics/objectives/Index', [
            'objectives' => $rows,
            'showArchived' => $showArchived,
            'periodTypes' => PlanningPeriodResolver::MODES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request);

        $p = $this->periods->resolve(
            $data['period_type'],
            $data['start_date'],
            $data['start_date'],
            $data['end_date'] ?? null,
        );

        $this->objectives->upsert(
            $p['start'],
            $p['end'],
            (float) $data['target_tons'],
            $request->user()?->id,
            $data['notes'] ?? null,
            null,
            $p['mode'],
        );

        return redirect()->route('logistics.objectives.index')
            ->with('success', 'Objectif enregistré.');
    }

    public function update(Request $request, FleetObjective $objective)
    {
        $data = $request->validate([
            'target_tons' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // Keep the objective's own period type and range; only the target changes.
        $this->objectives->upsert(
            $objective->start_date->copy(),
            $objective->end_date->copy(),
            (float) $data['target_tons'],
            $request->user()?->id,
            $data['notes'] ?? $objective->notes,
            null,
            $objective->period_type ?? FleetObjective::PERIOD_WEEK,
        );

        return redirect()->route('logistics.objectives.index')
            ->with('success', 'Objectif mis à jour.');
    }

    public function archive(Request $request, FleetObjective $objective)
    {
        $objective->archived_at = $objective->archived_at ? null : now();
        $objective->save();

        return redirect()->route('logistics.objectives.index')
            ->with('success', $objective->archived_at ? 'Objectif archivé.' : 'Objectif réactivé.');
    }

    /**
     * @return array{period_type:string,start_date:string,end_date:?string,target_tons:mixed,notes:?string}
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'period_type' => ['required', 'in:' . implode(',', PlanningPeriodResolver::MODES)],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'target_tons' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
