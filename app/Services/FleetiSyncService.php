<?php

namespace App\Services;

use App\Models\Truck;
use App\Repositories\TruckRepository;
use Illuminate\Support\Collection;
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
        private readonly UnauthorizedStopDetector $unauthorizedStopDetector
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

                // 6. Theft-detection layer: extend/close stops, classify them,
                //    and escalate any unknown-location stops to incidents.
                try {
                    $closedStops = $this->stopDetectorService->extendForTruck(
                        $truck->fresh(),
                        $snapshot
                    );
                    foreach ($closedStops as $stop) {
                        $classified = $this->placeClassifierService->classify($stop);
                        $summary['stops_closed']++;

                        $incident = $this->unauthorizedStopDetector->inspect($classified);
                        if ($incident) {
                            $summary['theft_incidents_opened']++;
                        }
                    }
                } catch (\Throwable $e) {
                    // Never block a sync on the theft-detection layer — it's
                    // best-effort and we log so ops can investigate.
                    Log::warning('Theft-detection layer failed during sync.', [
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
