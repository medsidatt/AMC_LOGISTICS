<?php

namespace App\Domain\Fuel\Classification;

/**
 * Immutable, read-only snapshot of the reference data the classifier needs to derive business facts
 * (truck by normalized registration, active drivers, truck→assigned-drivers, card→owning-truck,
 * existing transaction refs). Built once by {@see FuelImportReferenceReader}. Holds no Eloquent
 * models and performs no queries — it only answers lookups, keeping the classifier pure.
 */
final class FuelImportReference
{
    /**
     * @param  array<string, array{id:int, active:bool}>  $matriculeMap  normalized matricule → truck fact
     * @param  list<array{id:int, name:string}>  $drivers  active drivers
     * @param  array<int, list<int>>  $truckDriverIds  truck_id → active driver ids
     * @param  array<string, int>  $cardOwner  card_number → owning truck_id
     * @param  array<string, bool>  $existingRefs  set of transaction_ref already persisted
     */
    public function __construct(
        private readonly array $matriculeMap,
        private readonly array $drivers,
        private readonly array $truckDriverIds,
        private readonly array $cardOwner,
        private readonly array $existingRefs,
    ) {}

    /** @return array{id:int, active:bool}|null */
    public function truckFor(?string $normalizedRegistration): ?array
    {
        if ($normalizedRegistration === null) {
            return null;
        }

        return $this->matriculeMap[$normalizedRegistration] ?? null;
    }

    /** The normalized registration key that resolves to the given truck id (used by review re-classification). */
    public function registrationForTruckId(int $truckId): ?string
    {
        foreach ($this->matriculeMap as $registration => $truck) {
            if ($truck['id'] === $truckId) {
                return $registration;
            }
        }

        return null;
    }

    public function cardOwnerTruckId(?string $cardNumber): ?int
    {
        if ($cardNumber === null) {
            return null;
        }

        return $this->cardOwner[$cardNumber] ?? null;
    }

    public function refAlreadyExists(?string $transactionRef): bool
    {
        return $transactionRef !== null && isset($this->existingRefs[$transactionRef]);
    }

    /** @return list<int>|null  null when the truck has no active assignment on record */
    public function activeDriverIds(int $truckId): ?array
    {
        return $this->truckDriverIds[$truckId] ?? null;
    }

    /** Fuzzy-match a "Porteur" free-text against active drivers (≥2 shared name tokens of length ≥3). */
    public function driverIdForHolder(?string $holder): ?int
    {
        if ($holder === null || $holder === '') {
            return null;
        }
        $needle = strtoupper($this->stripAccents($holder));
        foreach ($this->drivers as $driver) {
            $tokens = array_filter(preg_split('/\s+/', strtoupper($this->stripAccents($driver['name']))), fn ($t) => strlen($t) >= 3);
            $matched = 0;
            foreach ($tokens as $token) {
                if (str_contains($needle, $token)) {
                    $matched++;
                }
            }
            if ($matched >= 2) {
                return $driver['id'];
            }
        }

        return null;
    }

    private function stripAccents(string $s): string
    {
        return strtr($s, [
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'À' => 'A',
        ]);
    }
}
