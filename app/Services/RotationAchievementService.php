<?php

namespace App\Services;

use App\Models\FleetObjective;
use App\Models\TransportTracking;
use App\Models\Truck;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Reconciles "rotations done" for a period from two sources:
 *   - Ticketed: transport_tracking rows (billing source of truth), by client_date.
 *   - GPS: freight loops (quarry→client→return) from trip segments.
 * A loop already linked to a ticket is not double-counted; a loop with no ticket
 * counts as done but is flagged "ticket manquant" (under-ticketing).
 * Tonnage for gps-only loops is estimated from the truck capacity.
 */
class RotationAchievementService
{
    public function __construct(
        private FreightLoopService $loops,
        private FleetCapacityService $capacity,
        private ObjectiveTargetResolver $objectiveResolver,
        private OperationsCalendarService $calendar,
    ) {}

    /**
     * Reconciled achievement for a period. Closed periods (already ended) are
     * cached — they only change if late tickets land, so the cache is keyed by
     * the objective's updated_at and given a short TTL.
     */
    public function forPeriod(Carbon $start, Carbon $end, ?string $viewMode = null): array
    {
        // Hierarchical target resolution (multi-period scoreboard) reads from
        // potentially several broader objectives, so it is not safely keyed by a
        // single objective's updated_at — compute it fresh. The legacy exact-match
        // path (dashboard / roster history) keeps its cache untouched.
        if ($viewMode !== null) {
            return $this->computePeriod($start, $end, $viewMode);
        }

        $isClosed = $end->copy()->endOfDay()->lt(Carbon::now()->startOfDay());

        if ($isClosed) {
            $obj = FleetObjective::where('start_date', $start->toDateString())
                ->where('end_date', $end->toDateString())
                ->first(['id', 'updated_at']);
            $key = 'rotach:' . $start->toDateString() . ':' . $end->toDateString() . ':' . ($obj?->updated_at?->timestamp ?? '0');

            return Cache::remember($key, now()->addHours(12), fn () => $this->computePeriod($start, $end));
        }

        return $this->computePeriod($start, $end);
    }

    private function computePeriod(Carbon $start, Carbon $end, ?string $viewMode = null): array
    {
        $startStr = $start->toDateString();
        $endStr = $end->toDateString();
        $defaultCap = $this->capacity->defaultCapacityTonnage();

        $trucks = Truck::where('is_active', true)->get(['id', 'matricule', 'capacity_tonnage'])->keyBy('id');

        // Ticketed rotations grouped per truck (one query).
        $ticketRows = TransportTracking::query()
            ->whereBetween('client_date', [$startStr, $endStr])
            ->selectRaw('truck_id, COUNT(*) as rotations, COALESCE(SUM(client_net_weight),0) as tons')
            ->groupBy('truck_id')
            ->get()
            ->keyBy('truck_id');

        // GPS freight loops; gps-only = loops with no ticket on any leg.
        $loops = $this->loops->loopsForPeriod($start, $end);
        $gpsAvailable = $loops->isNotEmpty();
        $gpsOnly = $loops->filter(fn ($l) => empty($l['transport_tracking_id']));
        $gpsOnlyByTruck = $gpsOnly->groupBy('truck_id')->map->count();

        // Target resolution. With a view mode (Réalisation scoreboard) the objective
        // must be EXACT for the selected period type (week→weekly, month→monthly,
        // year→annual) — no proration/aggregation fallback, so the wrong objective is
        // never attached to the period. Without a view mode (legacy callers) we keep
        // the exact same-period weekly snapshot.
        if ($viewMode !== null) {
            $resolved = $this->objectiveResolver->exactForMode($start, $end, $viewMode);
            $perTruckTargets = $resolved['per_truck'];
            $fleetTarget = $resolved['source'] !== 'none' ? $resolved['fleet'] : null;
            $targetSource = $resolved['source'];
            $targetCoverage = $resolved['coverage'];

            // Manual objectives ALWAYS take precedence over a derived reference. Order:
            //   1. exact objective for the selected period            (handled above)
            //   2. hierarchical-mean reference from manual CHILD       (month→weeks, year→months)
            //      objectives: Σ(manual children) + missing slots × mean(manual children)
            //   3. parent-remaining reference                         (ONLY when no manual children)
            //   4. no target
            // A reference is read-only: never persisted, never distributed to trucks, never
            // shown in Planning. Per-truck targets stay empty so the truck table shows
            // realized only (we never fabricate planning).
            if ($targetSource === 'none') {
                $reference = $this->hierarchicalChildReference($start, $end, $viewMode)
                    ?? $this->referenceTarget($start, $end, $viewMode, $defaultCap);
                if ($reference !== null) {
                    $fleetTarget = $reference;
                    $targetSource = 'estimated';
                    $targetCoverage = 0.0;
                }
            }
        } else {
            $objective = FleetObjective::with('truckTargets')
                ->where('period_type', FleetObjective::PERIOD_WEEK)
                ->where('start_date', $startStr)
                ->where('end_date', $endStr)
                ->first();
            $perTruckTargets = $objective
                ? $objective->truckTargets->mapWithKeys(fn ($t) => [(int) $t->truck_id => [
                    'target_rotations' => (int) $t->target_rotations,
                    'target_tons' => round((float) $t->target_tons, 2),
                ]])->all()
                : [];
            $fleetTarget = $objective
                ? ['target_rotations' => (int) $objective->target_rotations, 'target_tons' => round((float) $objective->target_tons, 2)]
                : null;
            $targetSource = $objective ? 'exact' : 'none';
            $targetCoverage = $objective ? 1.0 : 0.0;
        }

        $truckIds = collect($trucks->keys())
            ->merge($ticketRows->keys())
            ->merge($gpsOnlyByTruck->keys())
            ->merge(array_keys($perTruckTargets))
            ->unique()
            ->values();

        $perTruck = [];
        $sumTicketRot = 0; $sumTicketTons = 0.0; $sumGpsRot = 0; $sumGpsTons = 0.0;
        $sumTargetRot = 0; $sumTargetTons = 0.0; $sumTicketCap = 0.0;

        foreach ($truckIds as $id) {
            $truck = $trucks->get($id);
            // Per-truck rated capacity (v2 heterogeneous fleet); the fleet default
            // is only the fallback for trucks without a configured capacity.
            $cap = ($truck && (float) $truck->capacity_tonnage > 0) ? (float) $truck->capacity_tonnage : $defaultCap;

            $tk = $ticketRows->get($id);
            $tRot = (int) ($tk->rotations ?? 0);
            $tTons = round((float) ($tk->tons ?? 0), 2);

            $gRot = (int) ($gpsOnlyByTruck->get($id) ?? 0);
            $gTons = round($gRot * $cap, 2);

            $tt = $perTruckTargets[$id] ?? null;
            $tgtRot = (int) ($tt['target_rotations'] ?? 0);
            $tgtTons = round((float) ($tt['target_tons'] ?? 0), 2);

            $doneRot = $tRot + $gRot;
            $doneTons = round($tTons + $gTons, 2);

            // Fill rate = how full the truck was actually loaded, from the ticket
            // weights (bons) only — GPS-only loops have no weighed tonnage and are
            // estimated at full capacity, so they'd hide under-loading.
            $avgLoad = $tRot > 0 ? round($tTons / $tRot, 2) : 0.0;
            $fillPct = ($tRot > 0 && $cap > 0) ? min(100, (int) round($avgLoad / $cap * 100)) : null;

            $sumTicketRot += $tRot; $sumTicketTons += $tTons;
            $sumGpsRot += $gRot; $sumGpsTons += $gTons;
            $sumTargetRot += $tgtRot; $sumTargetTons += $tgtTons;
            $sumTicketCap += $tRot * $cap;

            $perTruck[] = [
                'truck_id' => (int) $id,
                'matricule' => $truck->matricule ?? '—',
                'capacity_tonnage' => round($cap, 2),
                'target_rotations' => $tgtRot,
                'target_tons' => $tgtTons,
                'ticketed_rotations' => $tRot,
                'ticketed_tons' => $tTons,
                'gps_only_rotations' => $gRot,
                'gps_only_tons' => $gTons,
                'done_rotations' => $doneRot,
                'done_tons' => $doneTons,
                'remaining_rotations' => max(0, $tgtRot - $doneRot),
                'remaining_tons' => round(max(0, $tgtTons - $doneTons), 2),
                // Per-truck progress is measured in rotations (the planning unit
                // shown in the table), so a completed rotation reads 100% even if
                // that trip carried less than the assumed truck capacity.
                'pct' => $this->pct($doneTons, $tgtTons, $doneRot, $tgtRot, true),
                'avg_load_t' => $avgLoad,
                'fill_pct' => $fillPct,
                'missing_tickets' => $gRot,
            ];
        }

        // Sort per-truck by rotations done (desc) for the table + leaderboard.
        usort($perTruck, fn ($a, $b) => $b['done_rotations'] <=> $a['done_rotations']);

        // Fleet target: prefer the resolved header; else the sum of per-truck targets.
        $targetTons = $fleetTarget ? (float) $fleetTarget['target_tons'] : round($sumTargetTons, 2);
        $targetRotations = $fleetTarget ? (int) $fleetTarget['target_rotations'] : $sumTargetRot;

        $doneRotations = $sumTicketRot + $sumGpsRot;
        $doneTons = round($sumTicketTons + $sumGpsTons, 2);

        // Fleet load fill (from weighed tickets only).
        $fleetAvgLoad = $sumTicketRot > 0 ? round($sumTicketTons / $sumTicketRot, 2) : 0.0;
        $fleetFillPct = ($sumTicketRot > 0 && $sumTicketCap > 0)
            ? min(100, (int) round($sumTicketTons / $sumTicketCap * 100))
            : null;

        return [
            'period' => ['start' => $startStr, 'end' => $endStr],
            'gps_available' => $gpsAvailable,
            'has_objective' => $targetSource !== 'none',
            'target_source' => $targetSource,
            'target_coverage' => $targetCoverage,
            'fleet' => [
                'target_rotations' => $targetRotations,
                'target_tons' => round($targetTons, 2),
                'ticketed_rotations' => $sumTicketRot,
                'ticketed_tons' => round($sumTicketTons, 2),
                'gps_only_rotations' => $sumGpsRot,
                'gps_only_tons' => round($sumGpsTons, 2),
                'done_rotations' => $doneRotations,
                'done_tons' => $doneTons,
                'remaining_rotations' => max(0, $targetRotations - $doneRotations),
                'remaining_tons' => round(max(0, $targetTons - $doneTons), 2),
                'pct' => $this->pct($doneTons, $targetTons, $doneRotations, $targetRotations),
                'avg_load_t' => $fleetAvgLoad,
                'fill_pct' => $fleetFillPct,
                'missing_tickets' => $gpsOnly->count(),
            ],
            'projection' => $this->projection($start, $end, $doneRotations, $doneTons, $targetRotations, $targetTons),
            'per_truck' => $perTruck,
            'leaderboard' => [
                'top' => array_slice(array_values(array_filter($perTruck, fn ($r) => $r['target_rotations'] > 0 || $r['done_rotations'] > 0)), 0, 3),
                'bottom' => array_slice(array_reverse(array_values(array_filter($perTruck, fn ($r) => $r['target_rotations'] > 0))), 0, 3),
            ],
            'missing_ticket_list' => $gpsOnly->take(50)->map(fn ($l) => [
                'truck_id' => $l['truck_id'],
                'matricule' => $trucks->get($l['truck_id'])->matricule ?? '—',
                'date' => $l['date'],
                'distance_km' => $l['distance_km'],
            ])->values()->all(),
        ];
    }

    /**
     * Step 2 of Réalisation resolution — the hierarchical-mean reference. A period with
     * no exact objective but with manual CHILD objectives inside it (month→weeks,
     * year→months) builds a read-only reference from those children:
     *
     *   reference = Σ(manual children) + (missing child slots × mean(manual children))
     *
     * Every child slot in the period contributes — the manually-planned ones at their
     * committed figure, the unplanned ones estimated at the mean of the planned
     * siblings. Manual planning always wins; estimates only fill the gaps. Example
     * (year, Jan=2200 Feb=2100 Mar=1900): 6200 + 9 × 2066.67 = 24 800.
     *
     * Returns null when the mode has no child level (week/custom) or no manual child
     * exists (→ fall through to the parent-remaining reference). Carries no per-truck
     * targets: like any reference it shows realized-only and is never persisted,
     * distributed, or surfaced in Planning.
     */
    private function hierarchicalChildReference(Carbon $start, Carbon $end, string $viewMode): ?array
    {
        $childType = match ($viewMode) {
            FleetObjective::PERIOD_MONTH => FleetObjective::PERIOD_WEEK,
            FleetObjective::PERIOD_YEAR => FleetObjective::PERIOD_MONTH,
            default => null, // WEEK / CUSTOM have no child level
        };
        if ($childType === null) {
            return null;
        }

        $children = FleetObjective::active()
            ->where('period_type', $childType)
            ->whereDate('start_date', '>=', $start->toDateString())
            ->whereDate('end_date', '<=', $end->toDateString())
            ->get(['target_tons', 'target_rotations']);

        if ($children->isEmpty()) {
            return null; // no manual children → parent-remaining reference instead
        }

        // Fill the unplanned sibling slots with the mean of the planned ones.
        $totalSlots = count($this->siblingPeriods($childType, $start->copy(), $end->copy()));
        $missing = max(0, $totalSlots - $children->count());

        $meanTons = (float) $children->avg('target_tons');
        $meanRot = (float) $children->avg('target_rotations');

        return [
            'target_tons' => round((float) $children->sum('target_tons') + $missing * $meanTons, 2),
            'target_rotations' => (int) round((float) $children->sum('target_rotations') + $missing * $meanRot),
        ];
    }

    /**
     * State 2 (Réalisation reporting reference). A period with NO manual objective that
     * sits inside a parent objective (month→year, week→month) gets a read-only benchmark:
     *
     *   parent_remaining = parent_target − Σ(manual child targets)
     *   reference        = parent_remaining × this period's share
     *
     * The share is weighted by operational days AND available fleet capacity across the
     * parent's *unallocated* sibling periods (never an equal split). The available fleet
     * is constant across future periods, so the capacity term scales every weight equally
     * — operational days drive the split, while capacity keeps the figure grounded in what
     * the fleet can actually move.
     *
     * This is a reporting benchmark, NOT an objective: never persisted, never distributed
     * to trucks, never surfaced in Planning. Returns null when there is no parent, the
     * mode has no parent (year/custom), the parent is already fully committed by manual
     * children, or the selected period is not an unallocated sibling.
     */
    private function referenceTarget(Carbon $start, Carbon $end, string $viewMode, float $defaultCap): ?array
    {
        [$parentType, $siblingType] = match ($viewMode) {
            FleetObjective::PERIOD_WEEK => [FleetObjective::PERIOD_MONTH, FleetObjective::PERIOD_WEEK],
            FleetObjective::PERIOD_MONTH => [FleetObjective::PERIOD_YEAR, FleetObjective::PERIOD_MONTH],
            default => [null, null], // YEAR / CUSTOM have no parent
        };
        if ($parentType === null) {
            return null;
        }

        $parent = FleetObjective::active()
            ->where('period_type', $parentType)
            ->whereDate('start_date', '<=', $start->toDateString())
            ->whereDate('end_date', '>=', $end->toDateString())
            ->orderByRaw('DATEDIFF(end_date, start_date) ASC')
            ->first(['start_date', 'end_date', 'target_tons']);
        if (! $parent) {
            return null;
        }

        // Manual children of the same level: their committed tonnage leaves the parent
        // budget, and their periods are excluded from the redistribution.
        $manualChildren = FleetObjective::active()
            ->where('period_type', $siblingType)
            ->whereDate('start_date', '>=', $parent->start_date->toDateString())
            ->whereDate('end_date', '<=', $parent->end_date->toDateString())
            ->get(['start_date', 'target_tons']);

        $parentRemaining = (float) $parent->target_tons - (float) $manualChildren->sum('target_tons');
        if ($parentRemaining <= 0) {
            return null; // parent already fully committed by manual children — no reference
        }

        $manualStarts = $manualChildren->map(fn ($c) => $c->start_date->toDateString())->all();

        // Available fleet capacity (constant across future periods) — keeps the reference
        // grounded in real fleet capability while operational days drive the split.
        $availableCapacityT = (float) Truck::query()
            ->where('is_active', true)
            ->where('is_available', true)
            ->sum('capacity_tonnage');
        $capacityWeight = $availableCapacityT > 0 ? $availableCapacityT : 1.0;

        // Weight (operational-days × capacity) of every UNALLOCATED sibling, plus the
        // selected period's own weight.
        $totalWeight = 0.0;
        $selectedWeight = 0.0;
        $selectedStart = $start->toDateString();
        foreach ($this->siblingPeriods($siblingType, $parent->start_date->copy(), $parent->end_date->copy()) as $sib) {
            if (in_array($sib['start']->toDateString(), $manualStarts, true)) {
                continue; // has a manual objective — excluded from redistribution
            }
            $weight = max(1, $this->calendar->operationalDays($sib['start'], $sib['end'])) * $capacityWeight;
            $totalWeight += $weight;
            if ($sib['start']->toDateString() === $selectedStart) {
                $selectedWeight = $weight;
            }
        }

        if ($totalWeight <= 0 || $selectedWeight <= 0) {
            return null; // selected period is not an unallocated sibling — no reference
        }

        $referenceTons = round($parentRemaining * ($selectedWeight / $totalWeight), 2);

        return [
            'target_tons' => $referenceTons,
            'target_rotations' => $defaultCap > 0 ? (int) round($referenceTons / $defaultCap) : 0,
        ];
    }

    /**
     * Canonical sibling periods inside a parent range: months (1st→last) for a year
     * parent, weeks (Mon→Sat) for a month parent — matching how manual objectives are
     * stored, so each manual child aligns to exactly one sibling.
     */
    private function siblingPeriods(string $siblingType, Carbon $parentStart, Carbon $parentEnd): array
    {
        $out = [];
        if ($siblingType === FleetObjective::PERIOD_MONTH) {
            $cursor = $parentStart->copy()->startOfMonth();
            while ($cursor->lte($parentEnd)) {
                $out[] = ['start' => $cursor->copy()->startOfMonth(), 'end' => $cursor->copy()->endOfMonth()];
                $cursor->addMonth();
            }
        } elseif ($siblingType === FleetObjective::PERIOD_WEEK) {
            $cursor = $parentStart->copy()->startOfWeek(Carbon::MONDAY);
            while ($cursor->lte($parentEnd)) {
                $out[] = ['start' => $cursor->copy(), 'end' => $cursor->copy()->addDays(5)];
                $cursor->addWeek();
            }
        }

        return $out;
    }

    /**
     * Per-truck "done today" for a single day, used by the daily planning board.
     * Returns ['by_truck' => [truck_id => [ticketed, gps_only, done, tons, missing]], 'gps_available' => bool].
     */
    public function forDay(Carbon $day): array
    {
        $dayStr = $day->toDateString();

        $tickets = TransportTracking::query()
            ->whereDate('client_date', $dayStr)
            ->selectRaw('truck_id, COUNT(*) as rotations, COALESCE(SUM(client_net_weight),0) as tons')
            ->groupBy('truck_id')
            ->get()
            ->keyBy('truck_id');

        $loops = $this->loops->loopsForPeriod($day->copy()->startOfDay(), $day->copy()->endOfDay());
        $gpsOnlyByTruck = $loops->filter(fn ($l) => empty($l['transport_tracking_id']))->groupBy('truck_id')->map->count();

        $byTruck = [];
        $truckIds = collect($tickets->keys())->merge($gpsOnlyByTruck->keys())->unique();
        foreach ($truckIds as $id) {
            $tk = $tickets->get($id);
            $tRot = (int) ($tk->rotations ?? 0);
            $gRot = (int) ($gpsOnlyByTruck->get($id) ?? 0);
            $byTruck[(int) $id] = [
                'ticketed' => $tRot,
                'gps_only' => $gRot,
                'done' => $tRot + $gRot,
                'tons' => round((float) ($tk->tons ?? 0), 2),
                'missing' => $gRot > 0,
            ];
        }

        return ['by_truck' => $byTruck, 'gps_available' => $loops->isNotEmpty()];
    }

    private function pct(float $doneTons, float $targetTons, int $doneRot, int $targetRot, bool $rotationFirst = false): ?int
    {
        if ($rotationFirst) {
            if ($targetRot > 0) return min(100, (int) round($doneRot / $targetRot * 100));
            if ($targetTons > 0) return min(100, (int) round($doneTons / $targetTons * 100));
            return null;
        }

        if ($targetTons > 0) return min(100, (int) round($doneTons / $targetTons * 100));
        if ($targetRot > 0) return min(100, (int) round($doneRot / $targetRot * 100));
        return null;
    }

    private function projection(Carbon $start, Carbon $end, int $doneRot, float $doneTons, int $targetRot, float $targetTons): array
    {
        // Pacing on operational working days (calendar-aware), not calendar days.
        $daysTotal = $this->calendar->operationalDays($start, $end);
        $today = Carbon::now();

        if ($today->lt($start)) {
            $daysElapsed = 0;
        } elseif ($today->gt($end)) {
            $daysElapsed = $daysTotal;
        } else {
            $daysElapsed = $this->calendar->operationalDays($start, $today);
        }

        $paceRot = $daysElapsed > 0 ? $doneRot / $daysElapsed : 0.0;
        $paceTons = $daysElapsed > 0 ? $doneTons / $daysElapsed : 0.0;
        $projectedRot = (int) round($paceRot * $daysTotal);
        $projectedTons = round($paceTons * $daysTotal, 2);

        return [
            'days_elapsed' => $daysElapsed,
            'days_total' => $daysTotal,
            'days_remaining' => max(0, $daysTotal - $daysElapsed),
            'pace_rotations_per_day' => round($paceRot, 2),
            'projected_rotations' => $projectedRot,
            'projected_tons' => $projectedTons,
            'on_track' => $targetTons > 0 ? $projectedTons >= $targetTons : ($targetRot > 0 ? $projectedRot >= $targetRot : true),
        ];
    }
}
