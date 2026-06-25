<?php

namespace App\Services;

use App\Models\CalendarDay;
use App\Models\FleetObjective;
use App\Models\OperationsCalendar;
use App\Models\Truck;
use App\Models\TruckAvailabilityWindow;
use App\Services\PlanningPeriodResolver;
use Carbon\Carbon;

/**
 * Single data-assembly path for the Planning workspace. Reused by the standalone
 * legacy routes (FleetObjectiveController, AvailabilityController,
 * OperationsCalendarController) AND by the Operations Planning tab
 * (OperationsController) so objectives, availability and calendar have one source.
 *
 * Read/presentation assembler only — writes still go through the existing save
 * paths (objective upsert, availability windows, calendar days).
 */
class PlanningWorkspaceService
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly FleetCapacityService $capacity,
        private readonly OperationsCalendarService $calendar,
    ) {}

    /** Objectives (commitments) list. */
    public function objectivesData(bool $showArchived = false): array
    {
        $objectives = FleetObjective::with('creator:id,name')
            ->when(! $showArchived, fn ($q) => $q->active())
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->limit(60)
            ->get();

        return [
            'objectives' => $objectives->map(fn (FleetObjective $o) => [
                'id' => $o->id,
                'period_type' => $o->period_type,
                'start_date' => $o->start_date->toDateString(),
                'end_date' => $o->end_date->toDateString(),
                'target_tons' => (float) $o->target_tons,
                'target_rotations' => (int) $o->target_rotations,
                'working_trucks' => $o->working_trucks,
                'notes' => $o->notes,
                'archived' => $o->archived_at !== null,
                'created_by' => $o->creator?->name,
            ])->values(),
        ];
    }

    /** Fleet availability for a month (KPIs + per-truck rows + downtime windows). */
    public function availabilityData(Carbon $anchor): array
    {
        $start = $anchor->copy()->startOfMonth();
        $end = $anchor->copy()->endOfMonth();

        $opDays = $this->calendar->operationalDays($start, $end);
        $opWeeks = $opDays / 6;
        $defaultRot = $this->capacity->defaultTargetRotationsPerWeek();

        $trucks = Truck::where('is_active', true)->orderBy('matricule')->get();

        $planned = [];
        foreach ($trucks as $t) {
            $rotPerWeek = $t->target_rotations_per_week ?? $defaultRot;
            $planned[$t->id] = round($rotPerWeek * $opWeeks, 2);
        }

        $fleet = $this->availability->forFleet($trucks, $start, $end->copy()->endOfDay(), $planned);

        $rows = $trucks->map(function (Truck $t) use ($fleet) {
            $a = $fleet['per_truck'][$t->id];
            return [
                'truck_id' => $t->id,
                'matricule' => $t->matricule,
                'operational_days' => $a['operational_days'],
                'lost_days' => $a['lost_days'],
                'availability_pct' => $a['availability_pct'],
                'available_capacity' => $a['available_capacity'],
                'lost_capacity' => $a['lost_capacity'],
                'source' => $a['source'],
            ];
        })->values();

        $windows = TruckAvailabilityWindow::query()
            ->with(['truck:id,matricule', 'creator:id,name'])
            ->overlapping($start, $end->copy()->endOfDay())
            ->orderByDesc('start_at')
            ->get()
            ->map(fn (TruckAvailabilityWindow $w) => [
                'id' => $w->id,
                'truck' => $w->truck?->matricule,
                'start_at' => $w->start_at->toDateString(),
                'end_at' => $w->end_at->toDateString(),
                'type' => $w->type,
                'reason' => $w->reason,
                'source' => $w->source,
                'created_by' => $w->creator?->name,
            ])->values();

        return [
            'period' => [
                'anchor' => $start->toDateString(),
                'label' => $start->translatedFormat('F Y'),
                'operational_days' => $opDays,
            ],
            'fleet' => [
                'operational_capacity' => $fleet['operational_capacity'],
                'available_capacity' => $fleet['available_capacity'],
                'lost_capacity' => $fleet['lost_capacity'],
                'availability_pct' => $fleet['availability_pct'],
                'downtime_impact' => $fleet['downtime_impact'],
            ],
            'trucks' => $rows,
            'windows' => $windows,
            'truckOptions' => $trucks->map(fn (Truck $t) => ['value' => $t->id, 'label' => $t->matricule])->values(),
            'types' => TruckAvailabilityWindow::TYPES,
        ];
    }

    /** Default operations calendar (working weekdays + exception days). */
    public function calendarData(): array
    {
        $calendar = OperationsCalendar::where('is_default', true)
            ->with(['days' => fn ($q) => $q->orderBy('date')])
            ->firstOrFail();

        return [
            'calendar' => [
                'id' => $calendar->id,
                'name' => $calendar->name,
                'working_weekdays' => $calendar->workingWeekdays(),
            ],
            'days' => $calendar->days->map(fn (CalendarDay $d) => [
                'id' => $d->id,
                'date' => $d->date->toDateString(),
                'day_type' => $d->day_type,
                'note' => $d->note,
            ])->values(),
        ];
    }

    /**
     * Planning Command Center — a lightweight operational briefing. Cheap reads
     * only (no per-truck availability loop, no config forms): the active objective,
     * a capacity estimate from settings + counts, period-to-date realisation from a
     * single tonnage aggregate, actionable risks, and the resolved next action.
     */
    public function commandCenterData(): array
    {
        $today = Carbon::now();

        // Active objective = the most specific non-archived objective covering today.
        $objective = FleetObjective::active()
            ->with('truckTargets')
            ->whereDate('start_date', '<=', $today->toDateString())
            ->whereDate('end_date', '>=', $today->toDateString())
            ->orderByRaw("FIELD(period_type, 'CUSTOM', 'WEEK', 'MONTH', 'YEAR')")
            ->first();

        if ($objective) {
            $start = $objective->start_date->copy();
            $end = $objective->end_date->copy();
            $periodType = $objective->period_type;
        } else {
            $start = $today->copy()->startOfWeek(Carbon::MONDAY);
            $end = $start->copy()->addDays(5);
            $periodType = FleetObjective::PERIOD_WEEK;
        }

        // Cheap fleet figures.
        $activeTrucks = Truck::where('is_active', true)->count();
        $availableTrucks = Truck::where('is_active', true)->where('is_available', true)->count();
        $unavailable = max(0, $activeTrucks - $availableTrucks);
        $availabilityRate = $activeTrucks > 0 ? (int) round($availableTrucks / $activeTrucks * 100) : null;

        $opTotal = max(0, $this->calendar->operationalDays($start, $end));
        $weeks = max(0.01, $opTotal / 6);
        $availableCapacity = (int) round(
            $availableTrucks
            * $this->capacity->defaultTargetRotationsPerWeek()
            * $weeks
            * $this->capacity->defaultCapacityTonnage()
        );

        $targetTons = $objective ? (float) $objective->target_tons : 0.0;
        $coverage = ($objective && $targetTons > 0) ? (int) round($availableCapacity / $targetTons * 100) : null;
        $gap = $objective ? (int) round($availableCapacity - $targetTons) : null;

        // Actionable constraints only. (Planning is NOT execution — no executed
        // tonnage, rotations, progress or trends here; that lives in Réalisation.)
        $risks = [];
        if ($unavailable > 0) {
            $risks[] = [
                'key' => 'trucks-unavailable',
                'message' => $unavailable.' camion'.($unavailable > 1 ? 's' : '').' indisponible'.($unavailable > 1 ? 's' : ''),
                'action' => '/planning/availability',
            ];
        }
        $calendarId = OperationsCalendar::where('is_default', true)->value('id');
        if ($calendarId) {
            $exception = CalendarDay::where('calendar_id', $calendarId)
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->whereIn('day_type', [CalendarDay::HOLIDAY, CalendarDay::SHUTDOWN])
                ->orderBy('date')
                ->first();
            if ($exception) {
                $risks[] = [
                    'key' => 'calendar-'.$exception->id,
                    'message' => 'Arrêt planifié le '.$exception->date->translatedFormat('j F'),
                    'action' => '/planning/calendar',
                ];
            }
        }
        if ($objective && $coverage !== null && $coverage < 100) {
            $risks[] = [
                'key' => 'capacity-deficit',
                'message' => 'Capacité insuffisante : déficit '.number_format(abs((int) $gap), 0, ',', ' ').' t',
                'action' => '/planning/availability',
            ];
        }

        // Recommended next action — always one explicit step.
        if (! $objective) {
            $action = ['label' => "Définir l'objectif", 'href' => '/logistics/objectives/create', 'rationale' => "Aucun objectif n'est défini pour la période."];
        } elseif ($coverage !== null && $coverage < 100) {
            $action = ['label' => 'Résoudre les contraintes', 'href' => '/planning/availability', 'rationale' => "Capacité insuffisante pour atteindre l'objectif."];
        } else {
            $action = ['label' => 'Ouvrir la répartition', 'href' => '/dispatch', 'rationale' => 'La planification est prête. Étape suivante : Répartition.'];
        }

        $situation = ! $objective
            ? 'Aucun objectif défini'
            : (($coverage !== null && $coverage < 100) ? 'Capacité insuffisante' : 'Objectif couvert');

        // Per-truck PLANNING allocation (the objective's frozen distribution) — this
        // is plan data, not execution. Cheap: the snapshot rows + one truck lookup.
        $allocation = [];
        if ($objective) {
            $targets = $objective->truckTargets;
            $truckMap = Truck::whereIn('id', $targets->pluck('truck_id'))
                ->get(['id', 'matricule', 'is_available', 'target_rotations_per_week'])
                ->keyBy('id');
            $defaultRot = $this->capacity->defaultTargetRotationsPerWeek();
            $allocation = $targets->map(function ($t) use ($truckMap, $weeks, $defaultRot) {
                $truck = $truckMap->get($t->truck_id);
                // Display-only: available rotation capacity for the period = per-truck
                // (or fallback) rotations/week × operational weeks. Does NOT change the
                // tonnage-driven planned rotations (target_rotations from the snapshot).
                $rotPerWeek = $truck?->target_rotations_per_week ?? $defaultRot;
                $capacityRot = (int) round($rotPerWeek * $weeks);
                $plannedRot = (int) $t->target_rotations;
                return [
                    'matricule' => $truck->matricule ?? '—',
                    'rotations' => $plannedRot,
                    'tonnage' => (int) round((float) $t->target_tons),
                    'capacity_rotations' => $capacityRot,
                    'utilisation' => $capacityRot > 0 ? (int) round($plannedRot / $capacityRot * 100) : null,
                    'available' => (bool) ($truck->is_available ?? true),
                ];
            })->sortBy('matricule')->values()->all();
        }

        return [
            'period' => ['type' => $periodType, 'start' => $start->toDateString(), 'end' => $end->toDateString()],
            'periodTypes' => PlanningPeriodResolver::MODES,
            'situation' => $situation,
            'objective' => $objective ? [
                'target_tons' => (int) round($targetTons),
                'target_rotations' => (int) $objective->target_rotations,
                'required_trucks' => (int) $objective->working_trucks,
                'period_type' => $objective->period_type,
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'notes' => $objective->notes,
            ] : null,
            'capacity' => [
                'available' => $availableCapacity,
                'availability_rate' => $availabilityRate,
                'coverage' => $coverage,
                'gap' => $gap,
            ],
            'allocation' => $allocation,
            'constraints' => $risks,
            'action' => $action,
        ];
    }

    /**
     * Parent-allocation context for the objective drawer — derived purely by date
     * containment (WEEK→MONTH, MONTH→YEAR). Validation / visibility only: it never
     * creates, links, distributes or blocks. Returns null when no parent applies
     * (YEAR / CUSTOM, or no containing parent objective exists).
     */
    public function parentAllocation(string $childType, string $childStart, string $childEnd): ?array
    {
        $parentType = match ($childType) {
            FleetObjective::PERIOD_WEEK => FleetObjective::PERIOD_MONTH,
            FleetObjective::PERIOD_MONTH => FleetObjective::PERIOD_YEAR,
            default => null,
        };
        if ($parentType === null) {
            return null;
        }

        $parent = FleetObjective::active()
            ->where('period_type', $parentType)
            ->whereDate('start_date', '<=', $childStart)
            ->whereDate('end_date', '>=', $childEnd)
            ->orderByRaw('DATEDIFF(end_date, start_date) ASC')
            ->first();
        if (! $parent) {
            return null;
        }

        // Sum the same-level siblings inside the parent's range, excluding the exact
        // period being created/edited so editing never double-counts itself.
        $allocated = (float) FleetObjective::active()
            ->where('period_type', $childType)
            ->whereDate('start_date', '>=', $parent->start_date->toDateString())
            ->whereDate('end_date', '<=', $parent->end_date->toDateString())
            ->where(fn ($q) => $q->whereDate('start_date', '!=', $childStart)->orWhereDate('end_date', '!=', $childEnd))
            ->sum('target_tons');

        $parentTarget = (float) $parent->target_tons;

        return [
            'parent_label' => $parentType === FleetObjective::PERIOD_YEAR ? 'Objectif annuel' : 'Objectif mensuel',
            'parent_target' => (int) round($parentTarget),
            'allocated' => (int) round($allocated),
            'remaining' => (int) round($parentTarget - $allocated),
        ];
    }
}
