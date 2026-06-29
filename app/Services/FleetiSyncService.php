<?php

namespace App\Services;

use App\Models\DailyDispatch;
use App\Models\DailyDispatchEvent;
use App\Models\Truck;
use App\Repositories\TruckRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class FleetiSyncService
{
    public function __construct(
        private readonly FleetiService $fleetiService,
        private readonly TruckRepository $truckRepository,
        private readonly KilometerService $kilometerService,
        private readonly EngineHoursService $engineHoursService,
        private readonly TelemetrySnapshotService $telemetrySnapshotService,
        private readonly FuelTrackingService $fuelTrackingService,
        private readonly FuelEventDetectorService $fuelEventDetector,
        private readonly StopDetectorService $stopDetectorService,
        private readonly PlaceClassifierService $placeClassifierService,
        private readonly ?DailyDispatchEventDeriver $eventDeriver = null,
        private readonly ?DispatchStatusResolver $statusResolver = null,
        private readonly ?DispatchEtaEstimator $etaEstimator = null
    ) {
    }

    public function syncKilometers(?string $customerReference = null, bool $onlyRequired = true): array
    {
        $candidateTrucks = $onlyRequired
            ? $this->truckRepository->getTrucksRequiringFleetiSync((int) config('maintenance.fleeti_sync_interval_minutes', 30))
            : $this->truckRepository->getAllForFleetiMatching();

        if ($candidateTrucks->isEmpty()) {
            return $this->emptySummary('No trucks required synchronization.');
        }

        $assetIds = $onlyRequired
            ? $candidateTrucks->pluck('fleeti_asset_id')->filter()->unique()->values()->all()
            : [];

        if ($onlyRequired && empty($assetIds)) {
            return $this->emptySummary('No trucks with Fleeti asset IDs required synchronization.');
        }

        $assets = $this->fleetiService->fetchAssets($customerReference, $assetIds);

        $summary = [
            'assets_received' => $assets->count(),
            'assets_with_km' => 0,
            'trucks_matched' => 0,
            'trucks_updated' => 0,
            'trackings_created' => 0,
            'snapshots_created' => 0,
            'engine_hour_trackings_created' => 0,
            'fuel_trackings_created' => 0,
            'fuel_events_detected' => 0,
            'stops_closed' => 0,
            'theft_incidents_opened' => 0,
            'assets_skipped' => 0,
            'errors' => [],
        ];

        foreach ($assets as $asset) {
            $assetId = data_get($asset, 'id');

            // Match truck first (cheap) before fetching full details
            $truck = $this->resolveTruck($asset, $candidateTrucks);
            if (! $truck) {
                $summary['assets_skipped']++;
                continue;
            }

            // Fetch full asset details — only this endpoint exposes fuel sensor data.
            $fullAsset = $asset;
            if ($assetId) {
                try {
                    $fetched = $this->fleetiService->fetchAssetById($assetId);
                    if ($fetched) {
                        $fullAsset = $fetched;
                    }
                } catch (\Throwable $e) {
                    Log::warning('Failed to fetch asset details from Fleeti.', [
                        'asset_id' => $assetId,
                        'truck_id' => $truck->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Extract every telemetry field in one pass
            $telemetry = $this->fleetiService->extractTelemetry($fullAsset);

            if ($telemetry['odometer_km'] !== null) {
                $summary['assets_with_km']++;
            }

            $summary['trucks_matched']++;

            try {
                // Keep raw asset IDs fresh
                $gatewayId = data_get($fullAsset, 'gateways.0.provider.gatewayId');
                $truckUpdates = array_filter([
                    'fleeti_asset_id' => $assetId ?: $truck->fleeti_asset_id,
                    'fleeti_gateway_id' => $gatewayId ?? $truck->fleeti_gateway_id,
                    'fleeti_last_fuel_level' => $telemetry['fuel_litres'],
                ], fn ($v) => ! is_null($v));

                if (! empty($truckUpdates)) {
                    $truck->update($truckUpdates);
                }

                // 1. Write the lossless snapshot (this also refreshes fleeti_last_* cache columns)
                $snapshot = $this->telemetrySnapshotService->record(
                    $truck->fresh(),
                    $telemetry,
                    $fullAsset,
                    'fleeti'
                );
                $summary['snapshots_created']++;

                $recordedAt = $snapshot->recorded_at ?? now();

                // 2. Derived: odometer tracking
                if ($telemetry['odometer_km'] !== null) {
                    $odoResult = $this->kilometerService->applyExternalOdometerReading(
                        $truck->fresh(),
                        (float) $telemetry['odometer_km'],
                        $recordedAt,
                        'fleeti',
                        $snapshot->id
                    );
                    if ($odoResult['updated']) {
                        $summary['trucks_updated']++;
                        $summary['trackings_created']++;
                    }
                }

                // 3. Derived: engine hours tracking
                if ($telemetry['engine_hours'] !== null) {
                    $hoursResult = $this->engineHoursService->applyExternalReading(
                        $truck->fresh(),
                        (float) $telemetry['engine_hours'],
                        $recordedAt,
                        'fleeti',
                        $snapshot->id
                    );
                    if ($hoursResult['updated']) {
                        $summary['engine_hour_trackings_created']++;
                    }
                }

                // 4. Derived: fuel tracking row (enriched with GPS + ignition context)
                if ($telemetry['fuel_litres'] !== null && (float) $telemetry['fuel_litres'] > 0) {
                    $fuelRow = $this->fuelTrackingService->record($truck->fresh(), $telemetry, $snapshot, 'fleeti');
                    if ($fuelRow) {
                        $summary['fuel_trackings_created']++;
                    }
                }

                // 5. Derived business events (refills, drops, theft-suspected)
                $event = $this->fuelEventDetector->analyze($truck->fresh(), $snapshot);
                if ($event) {
                    $summary['fuel_events_detected']++;
                }

                // 6. Stop / place classification — maintains TruckStop state and
                //    place anchoring used downstream by the reconciliation feed.
                try {
                    $closedStops = $this->stopDetectorService->extendForTruck(
                        $truck->fresh(),
                        $snapshot
                    );
                    foreach ($closedStops as $stop) {
                        $this->placeClassifierService->classify($stop);
                        $summary['stops_closed']++;
                    }
                } catch (\Throwable $e) {
                    // Never block a sync on the stop/place layer — best-effort.
                    Log::warning('Stop/place classification failed during sync.', [
                        'truck_id' => $truck->id,
                        'snapshot_id' => $snapshot->id ?? null,
                        'error' => $e->getMessage(),
                    ]);
                }
            } catch (ValidationException $e) {
                $summary['assets_skipped']++;
                $summary['errors'][] = [
                    'truck_id' => $truck->id,
                    'asset_id' => $assetId,
                    'message' => $e->getMessage(),
                ];
            } catch (\Throwable $e) {
                Log::error('Fleeti sync failed for truck.', [
                    'truck_id' => $truck->id,
                    'asset_id' => $assetId,
                    'error' => $e->getMessage(),
                ]);
                $summary['errors'][] = [
                    'truck_id' => $truck->id,
                    'asset_id' => $assetId,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $summary;
    }

    private function emptySummary(string $note): array
    {
        return [
            'assets_received' => 0,
            'assets_with_km' => 0,
            'trucks_matched' => 0,
            'trucks_updated' => 0,
            'trackings_created' => 0,
            'snapshots_created' => 0,
            'engine_hour_trackings_created' => 0,
            'fuel_trackings_created' => 0,
            'fuel_events_detected' => 0,
            'stops_closed' => 0,
            'theft_incidents_opened' => 0,
            'assets_skipped' => 0,
            'errors' => [],
            'note' => $note,
        ];
    }

    /**
     * Fast polling lane used by fleeti:sync-live-dispatch. Only handles trucks
     * already filtered by the caller (TruckRepository::getTrucksOnDispatchToday).
     * Skips odometer + engine-hours derivation — those are heavy and fine at
     * 30-min cadence via syncKilometers.
     */
    public function syncLive(?string $customerReference, Collection $trucks): array
    {
        if ($trucks->isEmpty()) {
            return $this->emptyLiveSummary('No trucks on today\'s dispatch.');
        }

        $assetIds = $trucks->pluck('fleeti_asset_id')->filter()->unique()->values()->all();
        if (empty($assetIds)) {
            return $this->emptyLiveSummary('Dispatched trucks have no Fleeti asset IDs.');
        }

        $assets = $this->fleetiService->fetchAssets($customerReference, $assetIds);

        $summary = [
            'assets_received' => $assets->count(),
            'trucks_matched' => 0,
            'snapshots_created' => 0,
            'fuel_events_detected' => 0,
            'stops_closed' => 0,
            'dispatch_events_created' => 0,
            'dispatches_updated' => 0,
            'assets_skipped' => 0,
            'errors' => [],
        ];

        foreach ($assets as $asset) {
            $assetId = data_get($asset, 'id');

            $truck = $this->resolveTruck($asset, $trucks);
            if (! $truck) {
                $summary['assets_skipped']++;
                continue;
            }

            // Per-truck lock to prevent overlapping fast ticks from racing.
            $lock = Cache::lock("fleeti:live:truck:{$truck->id}", 60);
            if (! $lock->get()) {
                $summary['assets_skipped']++;
                continue;
            }

            try {
                $this->processLiveTruck($truck, $asset, $assetId, $summary);
            } catch (\Throwable $e) {
                Log::error('Fleeti live sync failed for truck.', [
                    'truck_id' => $truck->id,
                    'asset_id' => $assetId,
                    'error' => $e->getMessage(),
                ]);
                $summary['errors'][] = [
                    'truck_id' => $truck->id,
                    'asset_id' => $assetId,
                    'message' => $e->getMessage(),
                ];
            } finally {
                optional($lock)->release();
            }
        }

        return $summary;
    }

    private function processLiveTruck(Truck $truck, array $asset, $assetId, array &$summary): void
    {
        $fullAsset = $asset;
        if ($assetId) {
            try {
                $fetched = $this->fleetiService->fetchAssetById($assetId);
                if ($fetched) {
                    $fullAsset = $fetched;
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to fetch asset details (live).', [
                    'asset_id' => $assetId,
                    'truck_id' => $truck->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $telemetry = $this->fleetiService->extractTelemetry($fullAsset);
        $summary['trucks_matched']++;

        // Live cache + raw snapshot (no kilometer/engine-hour writes — keep it lean)
        $gatewayId = data_get($fullAsset, 'gateways.0.provider.gatewayId');
        $truckUpdates = array_filter([
            'fleeti_asset_id' => $assetId ?: $truck->fleeti_asset_id,
            'fleeti_gateway_id' => $gatewayId ?? $truck->fleeti_gateway_id,
            'fleeti_last_fuel_level' => $telemetry['fuel_litres'],
        ], fn ($v) => ! is_null($v));
        if (! empty($truckUpdates)) {
            $truck->update($truckUpdates);
        }

        $snapshot = $this->telemetrySnapshotService->record(
            $truck->fresh(),
            $telemetry,
            $fullAsset,
            'fleeti'
        );
        $summary['snapshots_created']++;

        // Detect refill/drop/theft events (cheap)
        $fuelEvent = $this->fuelEventDetector->analyze($truck->fresh(), $snapshot);
        if ($fuelEvent) {
            $summary['fuel_events_detected']++;
        }

        // Stops & place classification (also cheap incremental work)
        $classifiedStops = [];
        try {
            $closedStops = $this->stopDetectorService->extendForTruck($truck->fresh(), $snapshot);
            foreach ($closedStops as $stop) {
                $classified = $this->placeClassifierService->classify($stop);
                $classifiedStops[] = $classified;
                $summary['stops_closed']++;
            }
        } catch (\Throwable $e) {
            Log::warning('Stop/place layer failed (live).', [
                'truck_id' => $truck->id,
                'snapshot_id' => $snapshot->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        // Materialise the dispatch event timeline + live status for the
        // active dispatch on this truck.
        $this->updateDispatchTimeline($truck, $snapshot, $classifiedStops, $fuelEvent, $summary);
    }

    private function updateDispatchTimeline(
        Truck $truck,
        $snapshot,
        array $classifiedStops,
        $fuelEvent,
        array &$summary
    ): void {
        if (! $this->eventDeriver || ! $this->statusResolver) {
            return; // back-compat: services optional
        }

        $dispatch = $this->resolveActiveDispatch($truck);
        if (! $dispatch) {
            return;
        }

        $newFuelEvents = $fuelEvent ? [$fuelEvent] : [];
        $newEvents = $this->eventDeriver->derive($dispatch, $truck, $snapshot, $classifiedStops, $newFuelEvents);
        $summary['dispatch_events_created'] += count($newEvents);

        // Reload recent events (today) to pass to the status resolver
        $recentEvents = DailyDispatchEvent::query()
            ->where('daily_dispatch_id', $dispatch->id)
            ->where('occurred_at', '>=', now()->subHours(24))
            ->orderBy('occurred_at')
            ->get();

        $resolution = $this->statusResolver->resolve($dispatch->fresh(), $snapshot, $recentEvents);
        $eta = $this->etaEstimator?->estimate($dispatch->fresh(), $recentEvents);

        $dispatch->forceFill([
            'current_status' => $resolution['status'],
            'current_status_at' => now(),
            'current_place_id' => $resolution['place']?->id,
            'last_event_id' => $recentEvents->last()?->id ?? $dispatch->last_event_id,
            'eta_at' => $eta,
        ])->save();

        $summary['dispatches_updated']++;
    }

    /**
     * Pick the dispatch this truck is currently executing. Prefers today's
     * dispatch; falls back to yesterday's if today's hasn't been published
     * yet and the truck is still on the road.
     */
    private function resolveActiveDispatch(Truck $truck): ?DailyDispatch
    {
        $today = Carbon::today()->toDateString();
        $todayDispatch = DailyDispatch::query()
            ->whereDate('dispatch_date', $today)
            ->where('truck_id', $truck->id)
            ->notified()
            ->orderByDesc('id')
            ->first();

        if ($todayDispatch && $todayDispatch->current_status !== DailyDispatch::STATUS_LIVE_TERMINE) {
            return $todayDispatch;
        }

        // Truck still finishing yesterday's trip?
        return DailyDispatch::query()
            ->whereDate('dispatch_date', Carbon::yesterday())
            ->where('truck_id', $truck->id)
            ->notified()
            ->where(function ($q) {
                $q->whereNull('current_status')
                    ->orWhere('current_status', '!=', DailyDispatch::STATUS_LIVE_TERMINE);
            })
            ->orderByDesc('id')
            ->first();
    }

    private function emptyLiveSummary(string $note): array
    {
        return [
            'assets_received' => 0,
            'trucks_matched' => 0,
            'snapshots_created' => 0,
            'fuel_events_detected' => 0,
            'stops_closed' => 0,
            'dispatch_events_created' => 0,
            'dispatches_updated' => 0,
            'assets_skipped' => 0,
            'errors' => [],
            'note' => $note,
        ];
    }

    private function resolveTruck(array $asset, Collection $trucks): ?Truck
    {
        $assetId = data_get($asset, 'id');
        if ($assetId) {
            $matched = $trucks->firstWhere('fleeti_asset_id', $assetId);
            if ($matched) {
                return $matched;
            }
        }

        $candidateMatricules = collect([
            data_get($asset, 'properties.LicensePlate'),
            data_get($asset, 'properties.licensePlate'),
            data_get($asset, 'name'),
        ])->filter();

        foreach ($candidateMatricules as $candidate) {
            $normalized = $this->fleetiService->normalizeMatricule((string) $candidate);
            if ($normalized === '') {
                continue;
            }

            $matched = $trucks->first(function (Truck $truck) use ($normalized) {
                return $this->fleetiService->normalizeMatricule((string) $truck->matricule) === $normalized;
            });

            if ($matched) {
                return $matched;
            }
        }

        return null;
    }
}
