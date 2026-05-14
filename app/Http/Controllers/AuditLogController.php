<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Auth\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditLogController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            $user = auth()->user();
            abort_unless($user && $user->hasAnyRole(['Admin', 'Super Admin']), 403);
            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $query = AuditLog::query()
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($userId = $request->integer('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($action = $request->string('action')->toString()) {
            $query->where('action', $action);
        }

        if ($subjectType = $request->string('subject_type')->toString()) {
            $query->where('subject_type', 'like', "%{$subjectType}%");
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

        $logs = $query->paginate(40)
            ->withQueryString()
            ->through(fn (AuditLog $l) => [
                'id' => $l->id,
                'user_name' => $l->user?->name ?? $l->user_name ?? 'système',
                'user_email' => $l->user?->email,
                'action' => $l->action,
                'subject_type' => $l->subject_type ? class_basename($l->subject_type) : null,
                'subject_label' => $l->subject_label,
                'subject_id' => $l->subject_id,
                'changes' => $l->changes,
                'ip_address' => $l->ip_address,
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
}
