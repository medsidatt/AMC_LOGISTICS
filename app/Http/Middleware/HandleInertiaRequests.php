<?php

namespace App\Http\Middleware;

use App\Models\DailyChecklistIssue;
use App\Models\ExpectedTransportTicket;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ] : null,
                'roles' => $user ? $user->getRoleNames()->toArray() : [],
                'permissions' => $user ? $user->getAllPermissions()->pluck('name')->toArray() : [],
            ],
            'notifications' => fn () => $user ? [
                'unread_count' => $user->unreadNotifications()->count(),
                'recent' => $user->notifications()->orderByDesc('created_at')->limit(8)->get()->map(fn ($n) => [
                    'id' => $n->id,
                    'data' => $n->data,
                    'read_at' => $n->read_at?->toIso8601String(),
                    'created_human' => $n->created_at?->diffForHumans(),
                ])->toArray(),
            ] : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            // Live counts for the Operations workflow sidebar badges (Réconciliation
            // + Exceptions). Lazy + gated so it only runs for operational users.
            'operationsBadges' => fn () => ($user && $user->can('daily-dispatch-list')) ? (function () {
                $missing = ExpectedTransportTicket::query()->status(ExpectedTransportTicket::STATUS_MISSING)->count();
                $flagged = DailyChecklistIssue::query()->where('flagged', true)->whereNull('resolved_at')->count();
                return ['missing' => $missing, 'exceptions' => $missing + $flagged];
            })() : null,
            'locale' => app()->getLocale(),
            'appName' => config('app.name', 'AMC Travaux SN'),
        ];
    }
}
