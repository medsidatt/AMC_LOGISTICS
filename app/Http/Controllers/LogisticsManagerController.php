<?php

namespace App\Http\Controllers;

use App\Models\DailyChecklist;
use App\Models\DailyChecklistIssue;
use App\Models\InspectionChecklistIssue;
use App\Services\SharePointDailyChecklistService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Logistics operational workflows: issue reporting/resolution and weekly
 * checklist validation. (The standalone "Tableau logistique" dashboard was
 * retired — it duplicated the role-landing dashboard; rotation validation was
 * dropped as an unused workflow.)
 */
class LogisticsManagerController extends Controller
{
    public function __construct(
        private readonly SharePointDailyChecklistService $sharePointDailyChecklistService,
    ) {
        $this->middleware('permission:logistics-dashboard', ['except' => [
            'pendingChecklists', 'validateChecklist',
            'resolveInspectionIssue',
        ]]);
        $this->middleware('permission:weekly-checklist-validate', ['only' => ['pendingChecklists', 'validateChecklist']]);
        $this->middleware('permission:checklist-issue-resolve', ['only' => ['resolveInspectionIssue']]);
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
