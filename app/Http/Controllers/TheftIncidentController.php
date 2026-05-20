<?php

namespace App\Http\Controllers;

use App\Models\Place;
use App\Models\Provider;
use App\Models\TheftIncident;
use App\Models\TransportTracking;
use App\Models\TripSegment;
use App\Models\Truck;
use App\Services\TheftIncidentService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

        $evidence = $theftIncident->evidence ?? [];

        // Surface whether any of the trip's segments now has a ticket linked
        // (it may have been entered between detection and viewing).
        $segmentIds = data_get($evidence, 'segment_ids', []);
        $linkedTicketId = null;
        if (! empty($segmentIds)) {
            $linkedTicketId = TripSegment::whereIn('id', $segmentIds)
                ->whereNotNull('transport_tracking_id')
                ->value('transport_tracking_id');
        }
        if ($linkedTicketId) {
            $evidence['linked_transport_tracking_id'] = $linkedTicketId;
        }

        // For the "create missing ticket" form we expose the list of
        // available providers (so the operator can pick one).
        $providersForForm = [];
        if ($theftIncident->type === TheftIncident::TYPE_UNTRACKED_TRIP) {
            $providersForForm = Provider::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->toArray();
        }

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
                'evidence' => $evidence,
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
            'providers' => $providersForForm,
        ]);
    }

    /**
     * Create a TransportTracking ticket from an untracked-trip incident.
     * Links the trip segments to the new ticket and dismisses the incident.
     */
    public function createTicket(Request $request, TheftIncident $theftIncident, TheftIncidentService $service)
    {
        abort_unless(
            $theftIncident->type === TheftIncident::TYPE_UNTRACKED_TRIP,
            422,
            'Cet incident ne supporte pas la création de bon de transport.',
        );

        $validated = $request->validate([
            'reference' => 'required|string|max:191|unique:transport_trackings,reference',
            'provider_id' => 'nullable|exists:providers,id',
            'product' => 'nullable|in:0/3,3/8,8/16',
            'provider_net_weight' => 'nullable|numeric|min:0',
            'client_net_weight' => 'nullable|numeric|min:0',
            'provider_gross_weight' => 'nullable|numeric|min:0',
            'client_gross_weight' => 'nullable|numeric|min:0',
        ]);

        $evidence = $theftIncident->evidence ?? [];
        $providerDate = $this->safeDate(data_get($evidence, 'provider_departure_at'));
        $clientDate = $this->safeDate(data_get($evidence, 'client_arrival_at'));
        $segmentIds = data_get($evidence, 'segment_ids', []);

        $ticket = DB::transaction(function () use ($theftIncident, $validated, $providerDate, $clientDate, $segmentIds) {
            $ticket = TransportTracking::create([
                'reference' => $validated['reference'],
                'truck_id' => $theftIncident->truck_id,
                'provider_id' => $validated['provider_id'] ?? null,
                'provider_date' => $providerDate,
                'client_date' => $clientDate,
                'product' => $validated['product'] ?? null,
                'provider_net_weight' => $validated['provider_net_weight'] ?? null,
                'client_net_weight' => $validated['client_net_weight'] ?? null,
                'provider_gross_weight' => $validated['provider_gross_weight'] ?? null,
                'client_gross_weight' => $validated['client_gross_weight'] ?? null,
            ]);

            // Backfill the link on every segment of this freight loop so the
            // detector doesn't re-flag the same trip on its next run.
            if (! empty($segmentIds)) {
                TripSegment::whereIn('id', $segmentIds)->update([
                    'transport_tracking_id' => $ticket->id,
                ]);
            }

            return $ticket;
        });

        $service->markDismissed(
            $theftIncident,
            $request->user(),
            'Bon de transport ' . $ticket->reference . ' créé depuis l\'incident.',
        );

        return redirect()
            ->route('theft-incidents.show', $theftIncident->id)
            ->with('success', 'Bon de transport ' . $ticket->reference . ' créé.');
    }

    private function safeDate(mixed $iso): ?string
    {
        if (! $iso) return null;
        try {
            return Carbon::parse($iso)->toDateString();
        } catch (\Throwable) {
            return null;
        }
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
