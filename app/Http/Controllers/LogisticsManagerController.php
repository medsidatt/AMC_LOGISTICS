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

        return view('pages.logistics.dashboard', [
            'dueEngineTrucks' => $dueEngineTrucks,
            'unresolvedIssues' => $unresolvedIssues,
            'lastDailyChecklists' => $lastDailyChecklists,
            'alerts' => $alerts,
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

        return view('pages.logistics.reports', [
            'issueFrequency' => $issueFrequency,
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
