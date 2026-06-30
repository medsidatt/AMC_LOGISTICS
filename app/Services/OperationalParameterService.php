<?php

namespace App\Services;

use App\Enums\OperationalParameterKey;
use App\Exceptions\MissingOperationalParameterException;
use App\Models\OperationalParameter;
use BackedEnum;
use Illuminate\Support\Facades\Cache;

/**
 * R1.1 — the single resolver for operational parameters (configurable VALUES only).
 *
 * Responsibilities (and nothing else):
 *   - load every active parameter ONCE and cache it (never two queries for one key);
 *   - expose typed getters (float/int/bool/string/enum);
 *   - report existence via has(); a missing key is an ERROR, not a guessed default;
 *   - invalidate the cache when a parameter changes.
 *
 * The service NEVER knows a business default (ADR-008). Defaults live in the seeder,
 * the migration history, and the ADRs. No business calculation belongs here.
 * See docs/operational-intelligence-architecture.md (L1).
 */
class OperationalParameterService
{
    public const CACHE_KEY = 'operational_parameters.map';

    /** Per-instance memo so repeated reads in one request never touch the cache store twice. */
    private ?array $resolved = null;

    /** The full key => typed-value map of active parameters (loaded once, cached). */
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

    public function has(OperationalParameterKey|string $key): bool
    {
        return array_key_exists($this->keyString($key), $this->map());
    }

    public function float(OperationalParameterKey|string $key): float
    {
        return (float) $this->resolve($key);
    }

    public function int(OperationalParameterKey|string $key): int
    {
        return (int) $this->resolve($key);
    }

    public function bool(OperationalParameterKey|string $key): bool
    {
        return (bool) $this->resolve($key);
    }

    public function string(OperationalParameterKey|string $key): string
    {
        return (string) $this->resolve($key);
    }

    /**
     * Resolve a stored value into a backed enum case.
     *
     * @template T of BackedEnum
     * @param  class-string<T>  $enumClass
     * @return T
     */
    public function enum(OperationalParameterKey|string $key, string $enumClass): BackedEnum
    {
        return $enumClass::from((string) $this->resolve($key));
    }

    /**
     * Persist a parameter value and invalidate the cache.
     * Only the value (and the editor) change; metadata is preserved.
     */
    public function set(OperationalParameterKey|string $key, mixed $value, ?int $userId = null): OperationalParameter
    {
        $keyString = $this->keyString($key);

        $parameter = OperationalParameter::query()->where('key', $keyString)->first();

        if ($parameter === null) {
            throw MissingOperationalParameterException::for($keyString);
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

    /** The single point where a missing parameter becomes a loud error. */
    private function resolve(OperationalParameterKey|string $key): mixed
    {
        $keyString = $this->keyString($key);
        $map = $this->map();

        if (! array_key_exists($keyString, $map)) {
            throw MissingOperationalParameterException::for($keyString);
        }

        return $map[$keyString];
    }

    private function keyString(OperationalParameterKey|string $key): string
    {
        return $key instanceof OperationalParameterKey ? $key->value : $key;
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
