<?php

namespace App\Http\Controllers;

use App\Models\Auth\User;
use App\Models\ExpectedTransportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TicketGapController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:live-fleet-view', ['only' => ['index']]);
        $this->middleware('permission:daily-dispatch-edit', ['only' => ['dismiss']]);
    }

    public function index(Request $request): Response
    {
        $rows = ExpectedTransportTicket::query()
            ->with([
                'truck:id,matricule',
                'provider:id,name',
                'dispatch:id,dispatch_date,driver_id',
                'dispatch.driver:id,name',
                'transportTracking:id,reference',
            ])
            ->whereIn('status', [
                ExpectedTransportTicket::STATUS_EXPECTED,
                ExpectedTransportTicket::STATUS_MISSING,
                ExpectedTransportTicket::STATUS_MATCHED,
            ])
            ->orderByDesc('loaded_at')
            ->limit(500)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'status' => $r->status,
                'loaded_at' => optional($r->loaded_at)?->toIso8601String(),
                'left_at' => optional($r->left_at)?->toIso8601String(),
                'deadline_at' => optional($r->deadline_at)?->toIso8601String(),
                'provider' => $r->provider ? ['id' => $r->provider->id, 'name' => $r->provider->name] : null,
                'truck' => $r->truck ? ['id' => $r->truck->id, 'matricule' => $r->truck->matricule] : null,
                'driver' => $r->dispatch?->driver ? ['id' => $r->dispatch->driver->id, 'name' => $r->dispatch->driver->name] : null,
                'dispatch_date' => optional($r->dispatch?->dispatch_date)?->toDateString(),
                'tracking' => $r->transportTracking ? [
                    'id' => $r->transportTracking->id,
                    'reference' => $r->transportTracking->reference,
                ] : null,
            ]);

        $counts = [
            'expected' => ExpectedTransportTicket::query()->status(ExpectedTransportTicket::STATUS_EXPECTED)->count(),
            'missing' => ExpectedTransportTicket::query()->status(ExpectedTransportTicket::STATUS_MISSING)->count(),
            'matched' => ExpectedTransportTicket::query()->status(ExpectedTransportTicket::STATUS_MATCHED)->count(),
        ];

        return Inertia::render('reports/TicketGap', [
            'rows' => $rows,
            'counts' => $counts,
        ]);
    }

    public function dismiss(Request $request, ExpectedTransportTicket $expected): RedirectResponse
    {
        $data = $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        /** @var User|null $user */
        $user = auth()->user();

        $expected->update([
            'status' => ExpectedTransportTicket::STATUS_DISMISSED,
            'dismissed_reason' => $data['reason'],
            'dismissed_by' => $user?->id,
        ]);

        return back()->with('success', 'Expected ticket dismissed.');
    }
}
