<?php

namespace App\Http\Controllers;

use App\Models\TheftIncident;
use App\Models\Truck;
use App\Services\TheftIncidentService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TheftIncidentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:logistics-dashboard', [
            'only' => ['index', 'show', 'update'],
        ]);
    }

    public function index(Request $request)
    {
        $filters = $request->only(['type', 'severity', 'status', 'truck_id', 'from', 'to']);

        $query = TheftIncident::query()
            ->with([
                'truck:id,matricule',
                'transportTracking:id,reference',
                'reviewer:id,name',
            ]);

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (! empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['truck_id'])) {
            $query->where('truck_id', $filters['truck_id']);
        }
        if (! empty($filters['from'])) {
            $query->whereDate('detected_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('detected_at', '<=', $filters['to']);
        }

        $incidents = $query
            ->orderByDesc('detected_at')
            ->limit(500)
            ->get()
            ->map(fn (TheftIncident $i) => [
                'id' => $i->id,
                'type' => $i->type,
                'severity' => $i->severity,
                'status' => $i->status,
                'title' => $i->title,
                'detected_at' => $i->detected_at?->format('d/m/Y H:i'),
                'detected_at_raw' => $i->detected_at?->toIso8601String(),
                'latitude' => $i->latitude,
                'longitude' => $i->longitude,
                'truck' => $i->truck ? [
                    'id' => $i->truck->id,
                    'matricule' => $i->truck->matricule,
                ] : null,
                'transport_tracking' => $i->transportTracking ? [
                    'id' => $i->transportTracking->id,
                    'reference' => $i->transportTracking->reference,
                ] : null,
                'reviewer' => $i->reviewer?->name,
                'reviewed_at' => $i->reviewed_at?->format('d/m/Y H:i'),
            ])
            ->values()
            ->all();

        $stats = [
            'pending' => TheftIncident::where('status', TheftIncident::STATUS_PENDING)->count(),
            'confirmed' => TheftIncident::where('status', TheftIncident::STATUS_CONFIRMED)->count(),
            'dismissed' => TheftIncident::where('status', TheftIncident::STATUS_DISMISSED)->count(),
            'reviewed' => TheftIncident::where('status', TheftIncident::STATUS_REVIEWED)->count(),
            'high' => TheftIncident::where('severity', TheftIncident::SEVERITY_HIGH)
                ->where('status', TheftIncident::STATUS_PENDING)
                ->count(),
            'last_7_days' => TheftIncident::where('detected_at', '>=', now()->subDays(7))->count(),
            'last_24h' => TheftIncident::where('detected_at', '>=', now()->subHours(24))->count(),
            'total' => TheftIncident::count(),
            'by_type' => TheftIncident::query()
                ->selectRaw('type, count(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray(),
        ];

        return Inertia::render('logistics/theft-incidents/Index', [
            'incidents' => $incidents,
            'filters' => $filters,
            'stats' => $stats,
            'trucks' => Truck::query()
                ->orderBy('matricule')
                ->get(['id', 'matricule'])
                ->toArray(),
        ]);
    }

    public function show(TheftIncident $theftIncident)
    {
        $theftIncident->load([
            'truck:id,matricule,fleeti_last_latitude,fleeti_last_longitude',
            'transportTracking:id,reference,provider_date,client_date,provider_net_weight,client_net_weight,gap',
            'tripSegment',
            'truckStop',
            'fuelEvent',
            'reviewer:id,name',
        ]);

        return Inertia::render('logistics/theft-incidents/Show', [
            'incident' => [
                'id' => $theftIncident->id,
                'type' => $theftIncident->type,
                'severity' => $theftIncident->severity,
                'status' => $theftIncident->status,
                'title' => $theftIncident->title,
                'detected_at' => $theftIncident->detected_at?->format('d/m/Y H:i'),
                'latitude' => $theftIncident->latitude,
                'longitude' => $theftIncident->longitude,
                'evidence' => $theftIncident->evidence,
                'review_notes' => $theftIncident->review_notes,
                'reviewed_at' => $theftIncident->reviewed_at?->format('d/m/Y H:i'),
                'reviewer' => $theftIncident->reviewer?->name,
                'truck' => $theftIncident->truck ? [
                    'id' => $theftIncident->truck->id,
                    'matricule' => $theftIncident->truck->matricule,
                ] : null,
                'transport_tracking' => $theftIncident->transportTracking ? [
                    'id' => $theftIncident->transportTracking->id,
                    'reference' => $theftIncident->transportTracking->reference,
                    'provider_date' => $theftIncident->transportTracking->provider_date?->format('d/m/Y'),
                    'client_date' => $theftIncident->transportTracking->client_date?->format('d/m/Y'),
                    'provider_net_weight' => $theftIncident->transportTracking->provider_net_weight,
                    'client_net_weight' => $theftIncident->transportTracking->client_net_weight,
                    'gap' => $theftIncident->transportTracking->gap,
                ] : null,
                'trip_segment_id' => $theftIncident->trip_segment_id,
                'truck_stop_id' => $theftIncident->truck_stop_id,
                'fuel_event_id' => $theftIncident->fuel_event_id,
            ],
        ]);
    }

    public function update(Request $request, TheftIncident $theftIncident, TheftIncidentService $service)
    {
        $validated = $request->validate([
            'action' => 'required|in:review,dismiss,confirm',
            'notes' => 'nullable|string|max:2000',
        ]);

        $user = $request->user();

        match ($validated['action']) {
            'review' => $service->markReviewed($theftIncident, $user, $validated['notes'] ?? null),
            'dismiss' => $service->markDismissed($theftIncident, $user, $validated['notes'] ?? null),
            'confirm' => $service->markConfirmed($theftIncident, $user, $validated['notes'] ?? null),
        };

        return redirect()
            ->route('theft-incidents.index')
            ->with('success', 'Incident mis à jour.');
    }
}
