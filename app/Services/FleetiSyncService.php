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
        private readonly KilometerService $kilometerService
    ) {
    }

    public function syncKilometers(?string $customerReference = null, bool $onlyRequired = true): array
    {
        $candidateTrucks = $onlyRequired
            ? $this->truckRepository->getTrucksRequiringFleetiSync((int) config('maintenance.fleeti_sync_interval_minutes', 30))
            : $this->truckRepository->getAllForFleetiMatching();

        if ($candidateTrucks->isEmpty()) {
            return [
                'assets_received' => 0,
                'assets_with_km' => 0,
                'trucks_matched' => 0,
                'trucks_updated' => 0,
                'trackings_created' => 0,
                'assets_skipped' => 0,
                'errors' => [],
                'note' => 'No trucks required synchronization.',
            ];
        }

        $assetIds = $onlyRequired
            ? $candidateTrucks->pluck('fleeti_asset_id')->filter()->unique()->values()->all()
            : [];

        if ($onlyRequired && empty($assetIds)) {
            return [
                'assets_received' => 0,
                'assets_with_km' => 0,
                'trucks_matched' => 0,
                'trucks_updated' => 0,
                'trackings_created' => 0,
                'assets_skipped' => 0,
                'errors' => [],
                'note' => 'No trucks with Fleeti asset IDs required synchronization.',
            ];
        }

        $assets = $this->fleetiService->fetchAssets($customerReference, $assetIds);
        $summary = [
            'assets_received' => $assets->count(),
            'assets_with_km' => 0,
            'trucks_matched' => 0,
            'trucks_updated' => 0,
            'trackings_created' => 0,
            'assets_skipped' => 0,
            'errors' => [],
        ];

        foreach ($assets as $asset) {
            $odometer = $this->fleetiService->extractOdometerKilometers($asset);
            if (is_null($odometer)) {
                $summary['assets_skipped']++;
                continue;
            }

            $summary['assets_with_km']++;
            $truck = $this->resolveTruck($asset, $candidateTrucks);
            if (! $truck) {
                $summary['assets_skipped']++;
                continue;
            }

            $summary['trucks_matched']++;

            try {
                $truck->update([
                    'fleeti_asset_id' => data_get($asset, 'id') ?: $truck->fleeti_asset_id,
                    'fleeti_gateway_id' => data_get($asset, 'gateways.0.provider.gatewayId') ?? $truck->fleeti_gateway_id,
                ]);

                $result = $this->kilometerService->applyExternalOdometerReading(
                    $truck->fresh(),
                    (float) $odometer,
                    now(),
                    'fleeti'
                );

                if ($result['updated']) {
                    $summary['trucks_updated']++;
                    $summary['trackings_created']++;
                }
            } catch (ValidationException $e) {
                $summary['assets_skipped']++;
                $summary['errors'][] = [
                    'truck_id' => $truck->id,
                    'asset_id' => data_get($asset, 'id'),
                    'message' => $e->getMessage(),
                ];
            } catch (\Throwable $e) {
                Log::error('Fleeti sync failed for truck.', [
                    'truck_id' => $truck->id,
                    'asset_id' => data_get($asset, 'id'),
                    'error' => $e->getMessage(),
                ]);
                $summary['errors'][] = [
                    'truck_id' => $truck->id,
                    'asset_id' => data_get($asset, 'id'),
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $summary;
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
