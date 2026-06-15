<?php

// PLAN-NOTE (à retirer plus tard) ────────────────────────────────────────────
// Phase actuelle : journal d'activité — UI/UX + export Excel.
// Hors scope (prochaines itérations) :
//   - Page détail (show) par entrée d'audit avec lien profond vers le sujet.
//   - Politique de rétention/purge : commande planifiée + champ dans FleetSettings.
//   - Onglet "Historique" sur chaque ressource (Truck/Driver/User/Project)
//     branché sur AuditLog via (subject_type, subject_id).
//   - Couverture étendue : signature inspection/maintenance, uploads SharePoint,
//     resets de mot de passe, attributions de rôle.
// ─────────────────────────────────────────────────────────────────────────────

namespace App\Http\Controllers;

use App\Exports\AuditLogExport;
use App\Models\AuditLog;
use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class AuditLogController extends Controller
{
    private const TRACKED_MODELS = [
        \App\Models\Driver::class,
        \App\Models\Auth\User::class,
        \App\Models\Truck::class,
        \App\Models\Project::class,
        \App\Models\TransportTracking::class,
        \App\Models\Provider::class,
        \App\Models\Transporter::class,
        \App\Models\Entity::class,
    ];

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:audit-log-view');
    }

    public function index(Request $request)
    {
        $query = AuditLog::query()
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $this->applyFilters($query, $request);

        $logs = $query->paginate(40)
            ->withQueryString()
            ->through(fn (AuditLog $l) => [
                'id' => $l->id,
                'user_name' => $l->user?->name ?? $l->user_name ?? 'système',
                'user_email' => $l->user?->email,
                'action' => $l->action,
                'subject_type' => $l->subject_type ? class_basename($l->subject_type) : null,
                'subject_type_full' => $l->subject_type,
                'subject_label' => $l->subject_label,
                'subject_id' => $l->subject_id,
                'changes' => $l->changes,
                'ip_address' => $l->ip_address,
                'user_agent' => $l->user_agent,
                'created_at' => $l->created_at?->format('d/m/Y H:i:s'),
            ]);

        $users = User::query()->orderBy('name')->get(['id', 'name'])->toArray();

        $actions = AuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->toArray();

        return Inertia::render('admin/AuditLogs', [
            'logs' => $logs,
            'users' => $users,
            'actions' => $actions,
            'subjectTypes' => $this->buildSubjectTypes(),
            'filters' => [
                'user_id' => $request->integer('user_id') ?: null,
                'action' => $request->string('action')->toString() ?: null,
                'subject_type' => $request->string('subject_type')->toString() ?: null,
                'search' => $request->string('search')->toString() ?: null,
                'from' => $request->string('from')->toString() ?: null,
                'to' => $request->string('to')->toString() ?: null,
            ],
        ]);
    }

    public function export(Request $request)
    {
        $filters = [
            'user_id' => $request->integer('user_id') ?: null,
            'action' => $request->string('action')->toString() ?: null,
            'subject_type' => $request->string('subject_type')->toString() ?: null,
            'search' => $request->string('search')->toString() ?: null,
            'from' => $request->string('from')->toString() ?: null,
            'to' => $request->string('to')->toString() ?: null,
        ];

        $filename = 'journal-activite-' . now()->format('Y-m-d-His') . '.xlsx';

        return Excel::download(new AuditLogExport($filters), $filename);
    }

    private function applyFilters(Builder $query, Request $request): Builder
    {
        if ($userId = $request->integer('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($action = $request->string('action')->toString()) {
            $query->where('action', $action);
        }

        if ($subjectType = $request->string('subject_type')->toString()) {
            $query->where('subject_type', $subjectType);
        }

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('user_name', 'like', "%{$search}%")
                  ->orWhere('subject_label', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%");
            });
        }

        if ($from = $request->string('from')->toString()) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->string('to')->toString()) {
            $query->whereDate('created_at', '<=', $to);
        }

        return $query;
    }

    private function buildSubjectTypes(): array
    {
        $distinct = AuditLog::query()
            ->select('subject_type')
            ->whereNotNull('subject_type')
            ->distinct()
            ->pluck('subject_type')
            ->all();

        $fqcns = array_values(array_unique(array_merge(self::TRACKED_MODELS, $distinct)));

        $types = array_map(
            fn (string $fqcn) => ['value' => $fqcn, 'label' => class_basename($fqcn)],
            $fqcns
        );

        usort($types, fn ($a, $b) => strcmp($a['label'], $b['label']));

        return $types;
    }
}
