<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FleetiService
{
    private const DEFAULT_TAKE = 200;

    /**
     * Fetch historical telemetry values (fuel, speed, etc.) from Fleeti.
     */
    public function fetchAllValues(string $customerReference, string $from, string $to, array $assetIds = []): Collection
    {
        $skip = 0;
        $results = collect();

        do {
            $query = array_filter([
                'CustomerReference' => $customerReference,
                'From' => $from,
                'To' => $to,
                'AssetIds' => !empty($assetIds) ? $assetIds : null,
                'Skip' => $skip,
                'Take' => self::DEFAULT_TAKE,
            ], fn ($value) => !is_null($value) && $value !== '');

            $response = Http::timeout(30)
                ->retry(2, 500)
                ->withHeaders($this->authHeaders())
                ->get($this->baseUrl() . '/v1/Asset/History/SearchAllValues', $query)
                ->throw()
                ->json();

            if (data_get($response, 'isSuccess') === false) {
                throw new \RuntimeException((string) data_get($response, 'message', 'Fleeti History API returned an error.'));
            }

            $batch = collect(data_get($response, 'results', []));
            $results = $results->merge($batch);
            $skip += self::DEFAULT_TAKE;
        } while ($batch->count() === self::DEFAULT_TAKE);

        return $results;
    }

    /**
     * Extract fuel litres from a telemetry/history record.
     * Looks for fuel-related fields in the response data.
     */
    public function extractFuelFromHistoryRecord(array $record): ?float
    {
        // Check common fuel field names in telemetry data
        $fuelFields = ['fuelLevel', 'fuel_level', 'fuelLiters', 'fuel', 'carburant',
            'FuelLevel', 'Fuel', 'fuelQuantity', 'fuelAmount', 'analog1'];

        foreach ($fuelFields as $field) {
            $value = data_get($record, $field);
            if (is_numeric($value) && (float) $value > 0) {
                return round((float) $value, 2);
            }
        }

        // Check nested data/values structures
        $values = data_get($record, 'values', data_get($record, 'data', []));
        if (is_array($values)) {
            foreach ($values as $key => $val) {
                $keyLower = Str::lower((string) $key);
                if (is_numeric($val) && (float) $val > 0 &&
                    Str::contains($keyLower, ['fuel', 'carburant', 'gasoil', 'litr'])) {
                    return round((float) $val, 2);
                }
            }
        }

        return null;
    }

    public function fetchAssets(?string $customerReference = null, array $assetIds = []): Collection
    {
        $skip = 0;
        $results = collect();

        do {
            $query = array_filter([
                'CustomerReference' => $customerReference,
                'Skip' => $skip,
                'Take' => self::DEFAULT_TAKE,
                'AssetIds' => ! empty($assetIds) ? $assetIds : null,
            ], fn ($value) => ! is_null($value) && $value !== '');

            $response = Http::timeout(20)
                ->retry(2, 500)
                ->withHeaders($this->authHeaders())
                ->get($this->baseUrl().'/v1/Asset/Search', $query)
                ->throw()
                ->json();

            if (data_get($response, 'isSuccess') === false) {
                throw new \RuntimeException((string) data_get($response, 'message', 'Fleeti API returned an error.'));
            }

            $batch = collect(data_get($response, 'results', []));
            $results = $results->merge($batch);
            $skip += self::DEFAULT_TAKE;
        } while ($batch->count() === self::DEFAULT_TAKE);

        return $results;
    }

    /**
     * Fetch a single asset by ID via /v1/Asset/Get.
     * This endpoint returns FULL accessory sensor data including fuel (sensorType=15),
     * which is NOT available via /v1/Asset/Search.
     */
    public function fetchAssetById(string $assetId): ?array
    {
        $response = Http::timeout(20)
            ->retry(2, 500)
            ->withHeaders($this->authHeaders())
            ->get($this->baseUrl() . '/v1/Asset/Get', ['Id' => $assetId])
            ->throw()
            ->json();

        if (data_get($response, 'isSuccess') === false) {
            return null;
        }

        // Asset/Get returns result as 'results' or 'result' depending on API version
        return data_get($response, 'results') ?? data_get($response, 'result');
    }

    public function extractOdometerKilometers(array $asset): ?float
    {
        $candidates = collect();

        $gateways = collect(data_get($asset, 'gateways', []));
        foreach ($gateways as $gateway) {
            foreach (collect(data_get($gateway, 'counters', [])) as $counter) {
                $value = data_get($counter, 'value');
                $unitType = Str::lower((string) data_get($counter, 'unitType', ''));
                if (is_numeric($value) && Str::contains($unitType, ['km', 'kilometer'])) {
                    $candidates->push((float) $value);
                }
            }

            foreach (collect(data_get($gateway, 'providerSensors', [])) as $sensor) {
                $value = data_get($sensor, 'value');
                $units = Str::lower((string) data_get($sensor, 'units', ''));
                if (is_numeric($value) && Str::contains($units, ['km', 'kilometer'])) {
                    $candidates->push((float) $value);
                }
            }

            foreach (collect(data_get($gateway, 'accessories', [])) as $accessory) {
                foreach (collect(data_get($accessory, 'providerSensors', [])) as $sensor) {
                    $value = data_get($sensor, 'value');
                    $units = Str::lower((string) data_get($sensor, 'units', ''));
                    if (is_numeric($value) && Str::contains($units, ['km', 'kilometer'])) {
                        $candidates->push((float) $value);
                    }
                }
            }
        }

        if ($candidates->isEmpty()) {
            return null;
        }

        return round((float) $candidates->max(), 2);
    }

    /**
     * Extract fuel level in litres from Fleeti asset data.
     *
     * IMPORTANT: This only works with data from /v1/Asset/Get (fetchAssetById),
     * NOT from /v1/Asset/Search (fetchAssets). The Search endpoint returns
     * accessories with empty providerSensors, while Get returns the full data.
     *
     * Per Fleeti support: fuel level sensors use sensorType = 15 inside
     * gateways[].accessories[].providerSensors with units = 'litre'.
     */
    public function extractFuelLitres(array $asset): ?float
    {
        $candidates = collect();
        $gateways = collect(data_get($asset, 'gateways', []));

        foreach ($gateways as $gateway) {
            // Primary: sensorType = 15 inside accessories (confirmed by Fleeti support)
            foreach (collect(data_get($gateway, 'accessories', [])) as $accessory) {
                foreach (collect(data_get($accessory, 'providerSensors', [])) as $sensor) {
                    if ((int) data_get($sensor, 'sensorType') === 15) {
                        $value = data_get($sensor, 'value');
                        if (is_numeric($value) && (float) $value >= 0) {
                            $candidates->push((float) $value);
                        }
                    }
                }
            }

            // Fallback: sensorType = 15 at gateway level
            foreach (collect(data_get($gateway, 'providerSensors', [])) as $sensor) {
                if ((int) data_get($sensor, 'sensorType') === 15) {
                    $value = data_get($sensor, 'value');
                    if (is_numeric($value) && (float) $value >= 0) {
                        $candidates->push((float) $value);
                    }
                }
            }
        }

        return $candidates->isNotEmpty() ? round($candidates->max(), 2) : null;
    }

    /**
     * @deprecated Use extractFuelLitres() instead
     */
    public function extractFuelLevel(array $asset): ?float
    {
        return $this->extractFuelLitres($asset);
    }

    private function isFuelUnit(string $unit): bool
    {
        return Str::contains($unit, ['fuel', 'litre', 'liter', 'l', 'gallon']);
    }

    private function isFuelName(string $name): bool
    {
        return Str::contains($name, ['fuel', 'carburant', 'gasoil', 'diesel', 'essence']);
    }

    public function normalizeMatricule(string $value): string
    {
        return Str::upper((string) preg_replace('/[^A-Za-z0-9]/', '', $value));
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.fleeti.base_url', 'https://api.fleeti.co'), '/');
    }

    private function apiKey(): string
    {
        $key = (string) config('services.fleeti.api_key');

        if ($key === '') {
            throw new \RuntimeException('FLEETI_API_KEY is not configured.');
        }

        return $key;
    }

    private function authHeaders(): array
    {
        $headers = [
            'x-api-key' => $this->apiKey(),
            'Accept' => 'application/json',
        ];

        $bearerToken = (string) config('services.fleeti.bearer_token', '');
        if ($bearerToken !== '') {
            $headers['Authorization'] = 'Bearer '.$bearerToken;
        }

        return $headers;
    }
}
