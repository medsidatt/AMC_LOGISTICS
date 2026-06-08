<?php

namespace App\Http\Controllers;

use App\Models\DailyDispatch;
use App\Models\DailyDispatchEvent;
use App\Models\Place;
use App\Models\Truck;
use App\Models\TruckTelemetrySnapshot;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LiveFleetController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:live-fleet-view');
    }

    public function index(Request $request): Response
    {
        $payload = $this->buildState();

        $places = Place::query()
            ->active()
            ->whereIn('type', [
                Place::TYPE_BASE,
                Place::TYPE_PROVIDER_SITE,
                Place::TYPE_CLIENT_SITE,
                Place::TYPE_FUEL_STATION,
                Place::TYPE_BORDER_POST,
            ])
            ->get(['id', 'name', 'type', 'latitude', 'longitude', 'radius_m'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'type' => $p->type,
                'latitude' => (float) $p->latitude,
                'longitude' => (float) $p->longitude,
                'radius_m' => (int) $p->radius_m,
            ]);

        return Inertia::render('logistics/LiveFleet', [
            'date' => Carbon::today()->toDateString(),
            'dispatches' => $payload['dispatches'],
            'events' => $payload['events'],
            'places' => $places,
        ]);
    }

    public function state(Request $request): JsonResponse
    {
        return response()->json($this->buildState());
    }

    public function show(DailyDispatch $dispatch): JsonResponse
    {
        $dispatch->load([
            'driver:id,name,phone',
            'truck:id,matricule,fleeti_last_latitude,fleeti_last_longitude,fleeti_last_speed_kmh,fleeti_last_fuel_level,fleeti_device_last_seen_at',
            'currentPlace:id,name,type',
            'wishProvider:id,name',
            'lastEvent',
        ]);

        $events = DailyDispatchEvent::query()
            ->where('daily_dispatch_id', $dispatch->id)
            ->with('place:id,name,type')
            ->orderBy('occurred_at')
            ->get()
            ->map(fn ($e) => $this->serializeEvent($e));

        $snapshots = TruckTelemetrySnapshot::query()
            ->where('truck_id', $dispatch->truck_id)
            ->where('recorded_at', '>=', now()->subHours(24))
            ->orderBy('recorded_at')
            ->get(['id', 'recorded_at', 'fuel_litres', 'speed_kmh', 'latitude', 'longitude', 'ignition_on'])
            ->map(fn ($s) => [
                'id' => $s->id,
                'recorded_at' => optional($s->recorded_at)?->toIso8601String(),
                'fuel_litres' => $s->fuel_litres !== null ? (float) $s->fuel_litres : null,
                'speed_kmh' => $s->speed_kmh !== null ? (float) $s->speed_kmh : null,
                'latitude' => $s->latitude !== null ? (float) $s->latitude : null,
                'longitude' => $s->longitude !== null ? (float) $s->longitude : null,
                'ignition_on' => $s->ignition_on,
            ]);

        return response()->json([
            'dispatch' => $this->serializeDispatch($dispatch),
            'events' => $events,
            'snapshots' => $snapshots,
            'expected_tickets' => $dispatch->expectedTickets()->with('transportTracking:id,reference')->get(),
        ]);
    }

    private function buildState(): array
    {
        $today = Carbon::today()->toDateString();
        $yesterday = Carbon::yesterday()->toDateString();

        $dispatches = DailyDispatch::query()
            ->where(function ($q) use ($today, $yesterday) {
                $q->whereDate('dispatch_date', $today)
                    ->orWhere(function ($q2) use ($yesterday) {
                        $q2->whereDate('dispatch_date', $yesterday)
                            ->where('current_status', '!=', DailyDispatch::STATUS_LIVE_TERMINE);
                    });
            })
            ->with([
                'driver:id,name,phone',
                'truck:id,matricule,fleeti_last_latitude,fleeti_last_longitude,fleeti_last_speed_kmh,fleeti_last_fuel_level,fleeti_device_last_seen_at,fleeti_last_ignition_on',
                'currentPlace:id,name,type',
                'lastEvent',
                'wishProvider:id,name',
            ])
            ->orderByDesc('dispatch_date')
            ->orderBy('current_status')
            ->get()
            ->map(fn (DailyDispatch $d) => $this->serializeDispatch($d));

        $events = DailyDispatchEvent::query()
            ->where('occurred_at', '>=', now()->subHours(12))
            ->with('place:id,name,type')
            ->orderByDesc('occurred_at')
            ->limit(200)
            ->get()
            ->map(fn ($e) => $this->serializeEvent($e));

        return [
            'dispatches' => $dispatches,
            'events' => $events,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function serializeDispatch(DailyDispatch $d): array
    {
        return [
            'id' => $d->id,
            'dispatch_date' => optional($d->dispatch_date)->toDateString(),
            'driver' => $d->driver ? [
                'id' => $d->driver->id,
                'name' => $d->driver->name,
            ] : null,
            'truck' => $d->truck ? [
                'id' => $d->truck->id,
                'matricule' => $d->truck->matricule,
                'latitude' => $d->truck->fleeti_last_latitude !== null ? (float) $d->truck->fleeti_last_latitude : null,
                'longitude' => $d->truck->fleeti_last_longitude !== null ? (float) $d->truck->fleeti_last_longitude : null,
                'speed_kmh' => $d->truck->fleeti_last_speed_kmh !== null ? (float) $d->truck->fleeti_last_speed_kmh : null,
                'fuel_litres' => $d->truck->fleeti_last_fuel_level !== null ? (float) $d->truck->fleeti_last_fuel_level : null,
                'ignition_on' => $d->truck->fleeti_last_ignition_on,
                'device_last_seen_at' => optional($d->truck->fleeti_device_last_seen_at)?->toIso8601String(),
            ] : null,
            'wish_provider' => $d->wishProvider ? [
                'id' => $d->wishProvider->id,
                'name' => $d->wishProvider->name,
            ] : null,
            'current_status' => $d->current_status,
            'current_status_at' => optional($d->current_status_at)?->toIso8601String(),
            'current_place' => $d->currentPlace ? [
                'id' => $d->currentPlace->id,
                'name' => $d->currentPlace->name,
                'type' => $d->currentPlace->type,
            ] : null,
            'eta_at' => optional($d->eta_at)?->toIso8601String(),
            'last_event' => $d->lastEvent ? $this->serializeEvent($d->lastEvent) : null,
            'notification_status' => $d->notification_status,
        ];
    }

    private function serializeEvent(DailyDispatchEvent $e): array
    {
        return [
            'id' => $e->id,
            'dispatch_id' => $e->daily_dispatch_id,
            'truck_id' => $e->truck_id,
            'type' => $e->type,
            'occurred_at' => optional($e->occurred_at)?->toIso8601String(),
            'latitude' => $e->latitude,
            'longitude' => $e->longitude,
            'place' => $e->place ? [
                'id' => $e->place->id,
                'name' => $e->place->name,
                'type' => $e->place->type,
            ] : null,
            'payload' => $e->payload,
            'source' => $e->source,
        ];
    }
}
