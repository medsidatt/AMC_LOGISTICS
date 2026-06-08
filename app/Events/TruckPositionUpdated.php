<?php

namespace App\Events;

use App\Models\Truck;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast every time a truck's live telemetry cache is refreshed (i.e.,
 * after TelemetrySnapshotService::record()). Browsers subscribed to the
 * `fleet.live` Pusher channel react in <1s — no more 60s polling lag on
 * /logistics/fleet-map or /logistics/live.
 *
 * Public channel: no Pusher auth flow needed (read-only positional data,
 * gated server-side via the page that opens the channel).
 */
class TruckPositionUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Truck $truck)
    {
    }

    public function broadcastOn(): Channel
    {
        return new Channel('fleet.live');
    }

    public function broadcastAs(): string
    {
        return 'truck.position.updated';
    }

    public function broadcastWith(): array
    {
        $t = $this->truck;

        return [
            'truck_id' => $t->id,
            'matricule' => $t->matricule,
            'latitude' => $t->fleeti_last_latitude !== null ? (float) $t->fleeti_last_latitude : null,
            'longitude' => $t->fleeti_last_longitude !== null ? (float) $t->fleeti_last_longitude : null,
            'heading' => $t->fleeti_last_heading_deg !== null ? (float) $t->fleeti_last_heading_deg : null,
            'speed' => $t->fleeti_last_speed_kmh !== null ? (float) $t->fleeti_last_speed_kmh : null,
            'movement_status' => $t->fleeti_last_movement_status,
            'ignition_on' => $t->fleeti_last_ignition_on,
            'fuel_level' => $t->fleeti_last_fuel_level !== null ? (float) $t->fleeti_last_fuel_level : null,
            'device_last_seen_at' => optional($t->fleeti_device_last_seen_at)?->toIso8601String(),
            'last_sync' => optional($t->fleeti_last_synced_at)?->toIso8601String(),
        ];
    }
}
