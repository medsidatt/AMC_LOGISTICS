<?php

namespace App\Http\Controllers;

use App\Models\DailyChecklist;
use App\Models\DailyChecklistIssue;
use App\Models\InspectionChecklist;
use App\Models\InspectionChecklistIssue;
use App\Models\LogisticsAlert;
use App\Models\TransportTracking;
use App\Models\Truck;
use App\Services\RotationService;
use App\Services\SharePointDailyChecklistService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogisticsManagerController extends Controller
{
    public function __construct(
        private readonly SharePointDailyChecklistService $sharePointDailyChecklistService,
        private readonly RotationService $rotationService,
    ) {
        $this->middleware('permission:logistics-dashboard', ['except' => [
            'pendingChecklists', 'validateChecklist',
            'pendingInspections', 'validateInspection',
            'resolveInspectionIssue',
        ]]);
        $this->middleware('permission:weekly-checklist-validate', ['only' => ['pendingChecklists', 'validateChecklist']]);
        $this->middleware('permission:inspection-validate', ['only' => ['pendingInspections', 'validateInspection']]);
        $this->middleware('permission:checklist-issue-resolve', ['only' => ['resolveInspectionIssue']]);
    }

    public function dashboard()
    {
        $dueEngineTrucks = Truck::query()
            ->where('is_active', true)
            ->get()
            ->filter(function (Truck $truck) {
                return (float) $truck->total_kilometers >= (float) $truck->nextMaintenanceAtKm();
            })
            ->values();

        $unresolvedIssues = DailyChecklistIssue::query()
            ->where('flagged', true)
            ->whereNull('resolved_at')
            ->with(['dailyChecklist.truck', 'dailyChecklist.driver'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $lastDailyChecklists = DailyChecklist::query()
            ->with(['truck', 'driver', 'issues'])
            ->orderByDesc('checklist_date')
            ->limit(20)
            ->get();

        $alerts = LogisticsAlert::query()
            ->whereNull('read_at')
            ->whereNull('resolved_at')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return \Inertia\Inertia::render('LogisticsDashboard', [
            'dueEngineTrucks' => $dueEngineTrucks->map(fn ($t) => [
                'id' => $t->id,
                'matricule' => $t->matricule,
                'total_kilometers' => $t->total_kilometers,
                'level' => $t->maintenanceLevelByType(),
            ])->values(),
            'unresolvedIssues' => $unresolvedIssues->map(fn ($i) => [
                'id' => $i->id,
                'description' => $i->issue_notes ?? $i->category ?? $i->description ?? '',
                'category' => $i->category,
                'checklist_date' => $i->dailyChecklist?->checklist_date,
                'truck' => $i->dailyChecklist?->truck?->matricule,
                'driver' => $i->dailyChecklist?->driver?->name,
            ]),
            'lastChecklists' => $lastDailyChecklists->map(fn ($c) => [
                'id' => $c->id,
                'checklist_date' => $c->checklist_date,
                'truck' => $c->truck?->matricule,
                'driver' => $c->driver?->name,
                'issues_count' => $c->issues->count(),
            ]),
            'alerts' => $alerts->map(fn ($a) => [
                'id' => $a->id,
                'type' => $a->type,
                'message' => $a->message,
                'created_at' => $a->created_at->format('d/m/Y H:i'),
            ]),
            'unvalidatedRotations' => $this->rotationService->getUnvalidatedRotations()->map(fn ($r) => [
                'id' => $r->id,
                'reference' => $r->reference,
                'truck' => $r->truck?->matricule,
                'driver' => $r->driver?->name,
                'start_km' => $r->start_km,
                'end_km' => $r->end_km,
                'distance' => $r->distance_km,
                'date' => $r->client_date?->format('d/m/Y') ?? $r->provider_date?->format('d/m/Y'),
            ])->values(),
        ]);
    }

    public function reports()
    {
        $from = now()->subDays(30);

        $issueFrequency = DailyChecklistIssue::query()
            ->where('flagged', true)
            ->where('created_at', '>=', $from)
            ->selectRaw('category, COUNT(*) as total, SUM(CASE WHEN resolved_at IS NULL THEN 1 ELSE 0 END) as open_count')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        $totalIssues = DailyChecklistIssue::query()
            ->where('flagged', true)
            ->where('created_at', '>=', $from)
            ->count();

        return \Inertia\Inertia::render('logistics/Reports', [
            'issueFrequency' => $issueFrequency->map(fn ($i) => [
                'category' => $i->category,
                'total' => $i->total,
                'open_count' => $i->open_count,
            ])->toArray(),
            'totalIssues' => $totalIssues,
            'from' => $from->toDateString(),
        ]);
    }

    public function resolveDailyIssue(Request $request, DailyChecklistIssue $issue)
    {
        $data = $request->validate([
            'resolution_notes' => 'required|string|max:10000',
        ]);

        $issue->update([
            'resolution_notes' => $data['resolution_notes'],
            'resolved_at' => now(),
            'resolved_by' => auth()->id(),
        ]);

        $dailyChecklist = $issue->dailyChecklist()->with(['issues'])->first();

        if (!empty($dailyChecklist?->sharepoint_item_id)) {
            $unresolvedCategories = $dailyChecklist->issues
                ->where('flagged', true)
                ->whereNull('resolved_at')
                ->pluck('category')
                ->values()
                ->all();

            $issueNotes = $dailyChecklist->issues
                ->where('flagged', true)
                ->map(function (DailyChecklistIssue $i) {
                    $base = $i->issue_notes ? (string) $i->issue_notes : '';
                    if ($i->resolved_at) {
                        return sprintf('%s: %s | Resolved: %s', $i->category, $base, $i->resolution_notes ?? '');
                    }
                    return sprintf('%s: %s', $i->category, $base);
                })
                ->values()
                ->all();

            $syncResult = $this->sharePointDailyChecklistService->updateIssueResolution(
                (string) $dailyChecklist->sharepoint_item_id,
                [
                    'IssueFlags' => !empty($unresolvedCategories) ? implode(',', $unresolvedCategories) : '',
                    'IssueNotes' => !empty($issueNotes) ? implode(' | ', $issueNotes) : null,
                ]
            );

            if (!$syncResult['success']) {
                Log::warning('SharePoint issue resolution sync failed', [
                    'dailyChecklistId' => $dailyChecklist->id,
                    'issueId' => $issue->id,
                    'message' => $syncResult['message'] ?? null,
                ]);
            }
        }

        return redirect()->back()->with('success', 'Issue resolue avec succes.');
    }

    public function validateRotation(TransportTracking $transportTracking)
    {
        $this->rotationService->validateRotation($transportTracking, auth()->user());
        return redirect()->back()->with('success', 'Rotation validée avec succès. Kilométrage mis à jour.');
    }

    public function pendingChecklists()
    {
        $pending = DailyChecklist::query()
            ->where('status', DailyChecklist::STATUS_PENDING)
            ->with(['truck:id,matricule', 'driver:id,name', 'issues'])
            ->orderByDesc('week_start_date')
            ->paginate(25)
            ->through(fn ($c) => [
                'id' => $c->id,
                'week_start_date' => $c->week_start_date?->format('d/m/Y'),
                'checklist_date' => $c->checklist_date?->format('d/m/Y'),
                'truck' => $c->truck?->matricule,
                'driver' => $c->driver?->name,
                'issues_count' => $c->issues->count(),
                'flagged_count' => $c->issues->where('flagged', true)->whereNull('resolved_at')->count(),
            ]);

        return \Inertia\Inertia::render('logistics/PendingChecklists', [
            'checklists' => $pending,
        ]);
    }

    public function validateChecklist(Request $request, DailyChecklist $dailyChecklist)
    {
        $data = $request->validate([
            'decision' => 'required|in:validated,rejected',
            'validation_notes' => 'nullable|string|max:2000',
        ]);

        $dailyChecklist->update([
            'status' => $data['decision'],
            'validated_by' => auth()->id(),
            'validated_at' => now(),
            'validation_notes' => $data['validation_notes'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Checklist hebdomadaire mise à jour.');
    }

    public function pendingInspections()
    {
        $pending = InspectionChecklist::query()
            ->pendingValidation()
            ->with(['truck:id,matricule', 'inspector:id,name', 'issues'])
            ->orderByDesc('inspection_date')
            ->paginate(25)
            ->through(fn ($i) => [
                'id' => $i->id,
                'inspection_date' => $i->inspection_date?->format('d/m/Y'),
                'truck' => $i->truck?->matricule,
                'inspector' => $i->inspector?->name,
                'category' => $i->category,
                'issues_count' => $i->issues->count(),
                'critical_count' => $i->issues->where('severity', 'critical')->count(),
            ]);

        return \Inertia\Inertia::render('logistics/PendingInspections', [
            'inspections' => $pending,
        ]);
    }

    public function validateInspection(Request $request, InspectionChecklist $inspection)
    {
        $data = $request->validate([
            'decision' => 'required|in:validated,rejected',
            'validation_notes' => 'nullable|string|max:2000',
        ]);

        $inspection->update([
            'status' => $data['decision'],
            'validated_by' => auth()->id(),
            'validated_at' => now(),
            'validation_notes' => $data['validation_notes'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Inspection mise à jour.');
    }

    public function resolveInspectionIssue(Request $request, InspectionChecklistIssue $issue)
    {
        $data = $request->validate([
            'resolution_notes' => 'required|string|max:10000',
        ]);

        $issue->update([
            'resolution_notes' => $data['resolution_notes'],
            'resolved_at' => now(),
            'resolved_by' => auth()->id(),
        ]);

        return redirect()->back()->with('success', 'Issue d\'inspection résolue.');
    }
}
