<?php

namespace App\Services;

use App\Models\OperationalParameter;
use Illuminate\Support\Facades\Cache;

/**
 * R1.1 — the single resolver for operational parameters (configurable VALUES only).
 *
 * Responsibilities (and nothing else):
 *   - load every active parameter ONCE and cache it (never two queries for one key);
 *   - expose typed getters (float/int/bool/string/array);
 *   - one fallback only — the $default passed by the caller;
 *   - invalidate the cache when a parameter changes.
 *
 * No business calculation belongs here. See docs/operational-intelligence-architecture.md (L1).
 */
class OperationalParameterService
{
    public const CACHE_KEY = 'operational_parameters.map';

    /** Per-instance memo so repeated reads in one request never touch the cache store twice. */
    private ?array $resolved = null;

    /**
     * The full key => typed-value map of active parameters.
     * Loaded once per process and cached across requests.
     */
    public function map(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        return $this->resolved = Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => $this->loadMap(),
        );
    }

    /** Raw resolved value (already cast to its declared type), or the single fallback. */
    public function get(string $key, mixed $default = null): mixed
    {
        $map = $this->map();

        return array_key_exists($key, $map) ? $map[$key] : $default;
    }

    public function float(string $key, float $default = 0.0): float
    {
        $value = $this->get($key);

        return $value === null ? $default : (float) $value;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key);

        return $value === null ? $default : (int) $value;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);

        return $value === null ? $default : (bool) $value;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->get($key);

        return $value === null ? $default : (string) $value;
    }

    public function array(string $key, array $default = []): array
    {
        $value = $this->get($key);

        return is_array($value) ? $value : $default;
    }

    /**
     * Persist a parameter value and invalidate the cache.
     * Only the value (and the editor) change; type/unit/category/description are preserved.
     */
    public function set(string $key, mixed $value, ?int $userId = null): OperationalParameter
    {
        $parameter = OperationalParameter::query()->where('key', $key)->first();

        if ($parameter === null) {
            throw new \InvalidArgumentException("Unknown operational parameter: {$key}");
        }

        $parameter->value = $this->stringify($value, $parameter->type);
        $parameter->updated_by = $userId;
        $parameter->save();

        $this->flush();

        return $parameter;
    }

    /** Clear the cached map (process memo + shared cache). */
    public function flush(): void
    {
        $this->resolved = null;
        Cache::forget(self::CACHE_KEY);
    }

    private function loadMap(): array
    {
        return OperationalParameter::query()
            ->where('is_active', true)
            ->get(['key', 'value', 'type'])
            ->mapWithKeys(fn (OperationalParameter $p): array => [
                $p->key => $this->cast($p->value, $p->type),
            ])
            ->all();
    }

    private function cast(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    private function stringify(mixed $value, string $type): string
    {
        return $type === 'json' ? (string) json_encode($value) : (string) $value;
    }
}
