<?php

namespace App\Http\Controllers;

use App\Models\DailyChecklist;
use App\Models\DailyChecklistIssue;
use App\Models\LogisticsAlert;
use App\Models\Truck;
use App\Services\SharePointDailyChecklistService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogisticsManagerController extends Controller
{
    public function __construct(
        private readonly SharePointDailyChecklistService $sharePointDailyChecklistService
    ) {
        $this->middleware('permission:logistics-dashboard');
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
                'created_at' => $a->created_at->format('Y-m-d H:i'),
            ]),
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
}
