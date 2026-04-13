<?php

namespace App\Services;

use Illuminate\Support\Carbon;
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

    // ---------------------------------------------------------------------
    // New telemetry extractors
    //
    // Each follows the same defensive pattern as extractOdometerKilometers /
    // extractFuelLitres: walk gateways/counters/providerSensors/accessories,
    // return null if nothing is found. Callers must not rely on any field.
    // ---------------------------------------------------------------------

    /**
     * Extract engine hours from Fleeti counters.
     * Looks for counters with unitType containing "hour" or "engine".
     */
    public function extractEngineHours(array $asset): ?float
    {
        $candidates = collect();

        foreach (collect(data_get($asset, 'gateways', [])) as $gateway) {
            foreach (collect(data_get($gateway, 'counters', [])) as $counter) {
                $value = data_get($counter, 'value');
                $unitType = Str::lower((string) data_get($counter, 'unitType', ''));
                $name = Str::lower((string) data_get($counter, 'name', ''));

                if (! is_numeric($value)) {
                    continue;
                }

                if (Str::contains($unitType, ['hour', 'hr'])
                    || Str::contains($name, ['engine', 'hour'])) {
                    $candidates->push((float) $value);
                }
            }

            foreach (collect(data_get($gateway, 'providerSensors', [])) as $sensor) {
                $value = data_get($sensor, 'value');
                $units = Str::lower((string) data_get($sensor, 'units', ''));
                $name = Str::lower((string) data_get($sensor, 'name', ''));

                if (! is_numeric($value)) {
                    continue;
                }

                if (Str::contains($units, 'hour')
                    || Str::contains($name, ['engine hour', 'enginehours', 'engine_hours'])) {
                    $candidates->push((float) $value);
                }
            }
        }

        return $candidates->isNotEmpty() ? round($candidates->max(), 2) : null;
    }

    /**
     * Extract current speed in km/h from Fleeti provider sensors.
     */
    public function extractSpeedKmh(array $asset): ?float
    {
        $candidates = collect();

        foreach (collect(data_get($asset, 'gateways', [])) as $gateway) {
            foreach (collect(data_get($gateway, 'providerSensors', [])) as $sensor) {
                $value = data_get($sensor, 'value');
                $units = Str::lower((string) data_get($sensor, 'units', ''));
                $name = Str::lower((string) data_get($sensor, 'name', ''));

                if (! is_numeric($value) || (float) $value < 0) {
                    continue;
                }

                if (Str::contains($units, ['km/h', 'kmh', 'kph'])
                    || Str::contains($name, ['speed', 'vitesse'])) {
                    $candidates->push((float) $value);
                }
            }

            // Top-level gateway speed field (Teltonika devices)
            $direct = data_get($gateway, 'speed');
            if (is_numeric($direct) && (float) $direct >= 0) {
                $candidates->push((float) $direct);
            }

            // Gateway.state.position.speed — Fleeti's canonical live telemetry payload
            $stateSpeed = data_get($gateway, 'state.position.speed');
            if (is_numeric($stateSpeed) && (float) $stateSpeed >= 0) {
                $candidates->push((float) $stateSpeed);
            }
        }

        // Asset-level fallback
        $assetSpeed = data_get($asset, 'speed') ?? data_get($asset, 'currentSpeed');
        if (is_numeric($assetSpeed) && (float) $assetSpeed >= 0) {
            $candidates->push((float) $assetSpeed);
        }

        return $candidates->isNotEmpty() ? round($candidates->max(), 2) : null;
    }

    /**
     * Extract GPS position information.
     * Returns ['lat' => float, 'lng' => float, 'heading' => float|null, 'accuracy' => float|null]
     */
    public function extractPosition(array $asset): ?array
    {
        // Try top-level asset position first
        $lat = data_get($asset, 'position.latitude')
            ?? data_get($asset, 'lastPosition.latitude')
            ?? data_get($asset, 'location.latitude')
            ?? data_get($asset, 'latitude');
        $lng = data_get($asset, 'position.longitude')
            ?? data_get($asset, 'lastPosition.longitude')
            ?? data_get($asset, 'location.longitude')
            ?? data_get($asset, 'longitude');

        $gatewayForHeading = null;

        // Fall back to gateway-level position (covers state.position.* as the
        // canonical Fleeti live telemetry shape)
        if (! is_numeric($lat) || ! is_numeric($lng)) {
            foreach (collect(data_get($asset, 'gateways', [])) as $gateway) {
                $gLat = data_get($gateway, 'state.position.location.latitude')
                    ?? data_get($gateway, 'state.position.latitude')
                    ?? data_get($gateway, 'position.latitude')
                    ?? data_get($gateway, 'lastPosition.latitude')
                    ?? data_get($gateway, 'latitude');
                $gLng = data_get($gateway, 'state.position.location.longitude')
                    ?? data_get($gateway, 'state.position.longitude')
                    ?? data_get($gateway, 'position.longitude')
                    ?? data_get($gateway, 'lastPosition.longitude')
                    ?? data_get($gateway, 'longitude');
                if (is_numeric($gLat) && is_numeric($gLng)) {
                    $lat = $gLat;
                    $lng = $gLng;
                    $gatewayForHeading = $gateway;
                    break;
                }
            }
        }

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        $heading = data_get($asset, 'position.heading')
            ?? data_get($asset, 'lastPosition.heading')
            ?? data_get($asset, 'heading')
            ?? data_get($asset, 'course')
            ?? data_get($gatewayForHeading, 'state.position.heading')
            ?? data_get($gatewayForHeading, 'position.heading');

        $accuracy = data_get($asset, 'position.accuracy')
            ?? data_get($asset, 'lastPosition.accuracy')
            ?? data_get($asset, 'accuracy')
            ?? data_get($gatewayForHeading, 'state.position.precision')
            ?? data_get($gatewayForHeading, 'position.precision');

        return [
            'lat' => round((float) $lat, 7),
            'lng' => round((float) $lng, 7),
            'heading' => is_numeric($heading) ? round((float) $heading, 1) : null,
            'accuracy' => is_numeric($accuracy) ? round((float) $accuracy, 1) : null,
        ];
    }

    /**
     * Extract ignition on/off state from Fleeti sensors.
     */
    public function extractIgnition(array $asset): ?bool
    {
        foreach (collect(data_get($asset, 'gateways', [])) as $gateway) {
            foreach (collect(data_get($gateway, 'providerSensors', [])) as $sensor) {
                $name = Str::lower((string) data_get($sensor, 'name', ''));
                $value = data_get($sensor, 'value');

                if (Str::contains($name, ['ignition', 'contact'])) {
                    if (is_bool($value)) {
                        return $value;
                    }
                    if (is_numeric($value)) {
                        return (float) $value > 0;
                    }
                    $strVal = Str::lower((string) $value);
                    if (in_array($strVal, ['on', 'true', '1', 'yes'], true)) {
                        return true;
                    }
                    if (in_array($strVal, ['off', 'false', '0', 'no'], true)) {
                        return false;
                    }
                }
            }

            $direct = data_get($gateway, 'ignition') ?? data_get($gateway, 'ignitionOn');
            if (is_bool($direct)) {
                return $direct;
            }
            if (is_numeric($direct)) {
                return (float) $direct > 0;
            }

            // Infer ignition from state.position.speed + state.movementStatus.
            // Fleeti doesn't always expose an explicit ignition flag, but a
            // parked (code=10) truck with speed=0 is effectively ignition-off.
            $stateCode = data_get($gateway, 'state.movementStatus');
            $stateSpeed = data_get($gateway, 'state.position.speed');
            if (is_numeric($stateCode) && (int) $stateCode === 10
                && is_numeric($stateSpeed) && (float) $stateSpeed < 1) {
                return false;
            }
            if (is_numeric($stateCode) && (int) $stateCode === 30) {
                return true;  // moving implies ignition on
            }
        }

        $assetLevel = data_get($asset, 'ignition') ?? data_get($asset, 'ignitionOn');
        if (is_bool($assetLevel)) {
            return $assetLevel;
        }
        if (is_numeric($assetLevel)) {
            return (float) $assetLevel > 0;
        }

        return null;
    }

    /**
     * Extract battery voltage from Fleeti sensors.
     */
    public function extractBatteryVoltage(array $asset): ?float
    {
        $candidates = collect();

        foreach (collect(data_get($asset, 'gateways', [])) as $gateway) {
            foreach (collect(data_get($gateway, 'providerSensors', [])) as $sensor) {
                $value = data_get($sensor, 'value');
                $units = Str::lower((string) data_get($sensor, 'units', ''));
                $name = Str::lower((string) data_get($sensor, 'name', ''));

                if (! is_numeric($value)) {
                    continue;
                }

                if ($units === 'v' || $units === 'volt' || $units === 'volts' || Str::contains($units, 'volt')) {
                    if (Str::contains($name, ['battery', 'voltage', 'batterie'])
                        || Str::contains($units, 'volt')) {
                        $candidates->push((float) $value);
                    }
                }
            }

            $direct = data_get($gateway, 'batteryVoltage') ?? data_get($gateway, 'battery');
            if (is_numeric($direct)) {
                $candidates->push((float) $direct);
            }

            // Fleeti state.battery.level is a percent (0–100), not a voltage.
            // We still surface it here to populate fleeti_last_battery_voltage
            // because the column is semantically "battery health"; theft-layer
            // doesn't distinguish between V and % today.
            $stateBattery = data_get($gateway, 'state.battery.level');
            if (is_numeric($stateBattery)) {
                $candidates->push((float) $stateBattery);
            }
        }

        return $candidates->isNotEmpty() ? round($candidates->max(), 2) : null;
    }

    /**
     * Extract signal strength (0-100 or RSSI) from gateway metadata.
     */
    public function extractSignalStrength(array $asset): ?int
    {
        $candidates = collect();

        foreach (collect(data_get($asset, 'gateways', [])) as $gateway) {
            foreach (['signalStrength', 'signal', 'rssi', 'gsmSignal'] as $key) {
                $value = data_get($gateway, $key);
                if (is_numeric($value)) {
                    $candidates->push((int) $value);
                }
            }

            // Fleeti state path — network.signalLevel (0–100) is the canonical value
            foreach (['state.network.signalLevel', 'state.position.signalLevel'] as $path) {
                $value = data_get($gateway, $path);
                if (is_numeric($value)) {
                    $candidates->push((int) $value);
                }
            }

            foreach (collect(data_get($gateway, 'providerSensors', [])) as $sensor) {
                $name = Str::lower((string) data_get($sensor, 'name', ''));
                $value = data_get($sensor, 'value');
                if (is_numeric($value) && Str::contains($name, ['signal', 'rssi', 'gsm'])) {
                    $candidates->push((int) $value);
                }
            }
        }

        if ($candidates->isEmpty()) {
            return null;
        }

        $max = $candidates->max();
        // Clamp to int8 range (tinyInteger in MySQL is -128..127)
        return max(-128, min(127, (int) $max));
    }

    /**
     * Extract movement status string ('moving' / 'idle' / 'parked' / 'unknown').
     *
     * Fleeti exposes this in three different shapes:
     *  - gateway.state.movementStatus: numeric code (10 = parked, 20 = idle, 30 = moving)
     *  - gateway.movementStatus: string
     *  - asset.movementStatus / movement / status: string
     */
    public function extractMovementStatus(array $asset): ?string
    {
        foreach (collect(data_get($asset, 'gateways', [])) as $gateway) {
            // Numeric code from state.movementStatus (canonical Fleeti shape)
            $stateCode = data_get($gateway, 'state.movementStatus');
            if (is_numeric($stateCode)) {
                return $this->movementCodeToString((int) $stateCode);
            }

            $raw = data_get($gateway, 'movementStatus')
                ?? data_get($gateway, 'movement')
                ?? data_get($gateway, 'status');

            if (is_numeric($raw)) {
                return $this->movementCodeToString((int) $raw);
            }

            if (is_string($raw) && $raw !== '') {
                return $this->normalizeMovementStatus($raw);
            }
        }

        $assetLevel = data_get($asset, 'movementStatus')
            ?? data_get($asset, 'movement')
            ?? data_get($asset, 'status');

        if (is_numeric($assetLevel)) {
            return $this->movementCodeToString((int) $assetLevel);
        }

        if (is_string($assetLevel) && $assetLevel !== '') {
            return $this->normalizeMovementStatus($assetLevel);
        }

        return null;
    }

    private function normalizeMovementStatus(string $raw): string
    {
        $lower = Str::lower($raw);
        if (Str::contains($lower, ['moving', 'drive', 'route', 'en route'])) {
            return 'moving';
        }
        if (Str::contains($lower, ['idle', 'idling'])) {
            return 'idle';
        }
        if (Str::contains($lower, ['park', 'stop', 'stationary'])) {
            return 'parked';
        }
        return Str::limit($lower, 18, '');
    }

    /**
     * Map Fleeti's numeric movement-status code to our string taxonomy.
     * Codes below are inferred from the public Fleeti API payloads.
     */
    private function movementCodeToString(int $code): string
    {
        return match ($code) {
            10 => 'parked',
            20 => 'idle',
            30 => 'moving',
            default => 'unknown',
        };
    }

    /**
     * Extract the timestamp the device was last seen online.
     */
    public function extractDeviceLastSeen(array $asset): ?Carbon
    {
        $candidates = [
            data_get($asset, 'lastSeen'),
            data_get($asset, 'lastSeenAt'),
            data_get($asset, 'lastUpdate'),
        ];

        foreach (collect(data_get($asset, 'gateways', [])) as $gateway) {
            $candidates[] = data_get($gateway, 'lastSeen');
            $candidates[] = data_get($gateway, 'lastSeenAt');
            $candidates[] = data_get($gateway, 'lastUpdate');
            // Fleeti's live-telemetry timestamp
            $candidates[] = data_get($gateway, 'state.updatedAt');
            $candidates[] = data_get($gateway, 'state.position.updatedAt');
        }

        foreach ($candidates as $candidate) {
            if ($candidate === null || $candidate === '') {
                continue;
            }
            try {
                return Carbon::parse($candidate);
            } catch (\Throwable $e) {
                // Skip unparseable dates
            }
        }

        return null;
    }

    /**
     * Find the most recent "lastUpdate" among all counters/sensors — this is
     * the effective "recorded_at" of the reading, not our local sync time.
     */
    public function extractRecordedAt(array $asset): ?Carbon
    {
        $latest = null;

        foreach (collect(data_get($asset, 'gateways', [])) as $gateway) {
            foreach (collect(data_get($gateway, 'counters', [])) as $counter) {
                $latest = $this->maxCarbon($latest, data_get($counter, 'lastUpdate'));
            }
            foreach (collect(data_get($gateway, 'providerSensors', [])) as $sensor) {
                $latest = $this->maxCarbon($latest, data_get($sensor, 'lastUpdate'));
            }
            foreach (collect(data_get($gateway, 'accessories', [])) as $accessory) {
                foreach (collect(data_get($accessory, 'providerSensors', [])) as $sensor) {
                    $latest = $this->maxCarbon($latest, data_get($sensor, 'lastUpdate'));
                }
            }
            $latest = $this->maxCarbon($latest, data_get($gateway, 'lastUpdate'));
            // Fleeti live-telemetry timestamps
            $latest = $this->maxCarbon($latest, data_get($gateway, 'state.updatedAt'));
            $latest = $this->maxCarbon($latest, data_get($gateway, 'state.position.updatedAt'));
        }

        return $latest ?: $this->extractDeviceLastSeen($asset);
    }

    private function maxCarbon(?Carbon $current, $candidate): ?Carbon
    {
        if ($candidate === null || $candidate === '') {
            return $current;
        }
        try {
            $parsed = Carbon::parse($candidate);
        } catch (\Throwable $e) {
            return $current;
        }
        if ($current === null || $parsed->greaterThan($current)) {
            return $parsed;
        }
        return $current;
    }

    /**
     * Convenience: extract every telemetry field at once.
     * Keys are always present — values may be null when the field isn't available.
     *
     * @return array{
     *   recorded_at: ?Carbon,
     *   odometer_km: ?float,
     *   engine_hours: ?float,
     *   fuel_litres: ?float,
     *   speed_kmh: ?float,
     *   latitude: ?float,
     *   longitude: ?float,
     *   heading_deg: ?float,
     *   gps_accuracy_m: ?float,
     *   ignition_on: ?bool,
     *   movement_status: ?string,
     *   battery_voltage: ?float,
     *   signal_strength: ?int,
     *   device_last_seen_at: ?Carbon,
     * }
     */
    public function extractTelemetry(array $asset): array
    {
        $position = $this->extractPosition($asset);

        return [
            'recorded_at' => $this->extractRecordedAt($asset),
            'odometer_km' => $this->extractOdometerKilometers($asset),
            'engine_hours' => $this->extractEngineHours($asset),
            'fuel_litres' => $this->extractFuelLitres($asset),
            'speed_kmh' => $this->extractSpeedKmh($asset),
            'latitude' => $position['lat'] ?? null,
            'longitude' => $position['lng'] ?? null,
            'heading_deg' => $position['heading'] ?? null,
            'gps_accuracy_m' => $position['accuracy'] ?? null,
            'ignition_on' => $this->extractIgnition($asset),
            'movement_status' => $this->extractMovementStatus($asset),
            'battery_voltage' => $this->extractBatteryVoltage($asset),
            'signal_strength' => $this->extractSignalStrength($asset),
            'device_last_seen_at' => $this->extractDeviceLastSeen($asset),
        ];
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
