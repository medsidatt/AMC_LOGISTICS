<?php

namespace App\Domain\Fuel\Classification;

use App\Models\Driver;
use App\Models\FuelCardTransaction;
use App\Models\Truck;
use App\Models\TruckDriverAssignment;
use Illuminate\Support\Collection;

/**
 * Query-ONLY reader that builds an immutable {@see FuelImportReference} snapshot from the current DB
 * state (trucks, active drivers, active assignments, card→truck history, existing transaction refs).
 * The only component here that touches Eloquent — so the classifier itself stays pure and never queries.
 * Reuses the resolution shape of the legacy validator; introduces no new business rules.
 */
class FuelImportReferenceReader
{
    public function read(): FuelImportReference
    {
        $matriculeMap = Truck::query()->get(['id', 'matricule', 'is_active'])
            ->mapWithKeys(fn (Truck $t) => [
                $this->normalize((string) $t->matricule) => ['id' => (int) $t->id, 'active' => (bool) $t->is_active],
            ])->all();

        $drivers = Driver::query()->where('is_active', true)->get(['id', 'name'])
            ->map(fn (Driver $d) => ['id' => (int) $d->id, 'name' => (string) $d->name])->values()->all();

        $truckDriverIds = TruckDriverAssignment::query()->whereNull('ended_at')->get(['truck_id', 'driver_id'])
            ->groupBy('truck_id')
            ->map(fn (Collection $g) => $g->pluck('driver_id')->map(fn ($v) => (int) $v)->values()->all())
            ->all();

        $cardOwner = FuelCardTransaction::query()
            ->whereNotNull('card_number')->whereNotNull('truck_id')
            ->selectRaw('card_number, truck_id, COUNT(*) c')->groupBy('card_number', 'truck_id')->get()
            ->groupBy('card_number')
            ->map(fn (Collection $g) => (int) $g->sortByDesc('c')->first()->truck_id)
            ->all();

        $existingRefs = FuelCardTransaction::query()->whereNotNull('transaction_ref')
            ->pluck('transaction_ref')->flip()->map(fn () => true)->all();

        return new FuelImportReference($matriculeMap, $drivers, $truckDriverIds, $cardOwner, $existingRefs);
    }

    private function normalize(string $matricule): string
    {
        return strtoupper(preg_replace('/[\s\-]+/', '', $matricule));
    }
}
