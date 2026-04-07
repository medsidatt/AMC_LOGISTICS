<?php

namespace App\Services;

use App\Models\Auth\User;
use App\Models\TransportTracking;
use App\Models\Truck;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RotationService
{
    public function __construct(
        private KilometerService $kilometerService,
        private MaintenanceStatusService $maintenanceStatusService,
    ) {}

    public function startRotation(TransportTracking $rotation, float $startKm, Truck $truck): void
    {
        if ($startKm < $truck->total_kilometers - 50) { // 50km tolerance for odometer variance
            throw ValidationException::withMessages([
                'start_km' => "Le kilométrage de départ ({$startKm}) ne peut pas être inférieur au compteur du camion ({$truck->total_kilometers}).",
            ]);
        }

        $rotation->update(['start_km' => $startKm]);
    }

    public function completeRotation(TransportTracking $rotation, float $endKm): void
    {
        if ($rotation->start_km === null) {
            throw ValidationException::withMessages([
                'end_km' => 'Le kilométrage de départ doit être renseigné avant le kilométrage de fin.',
            ]);
        }

        if ($endKm <= $rotation->start_km) {
            throw ValidationException::withMessages([
                'end_km' => "Le kilométrage de fin ({$endKm}) doit être supérieur au kilométrage de départ ({$rotation->start_km}).",
            ]);
        }

        $distance = $endKm - $rotation->start_km;
        $maxDistance = config('maintenance.max_single_trip_distance_km', 2000);

        if ($distance > $maxDistance) {
            throw ValidationException::withMessages([
                'end_km' => "La distance de {$distance} km dépasse le maximum autorisé ({$maxDistance} km) pour un seul trajet.",
            ]);
        }

        $rotation->update(['end_km' => $endKm]);
    }

    public function validateRotation(TransportTracking $rotation, User $validator): void
    {
        if ($rotation->is_validated) {
            throw ValidationException::withMessages([
                'rotation' => 'Cette rotation est déjà validée.',
            ]);
        }

        if ($rotation->start_km === null || $rotation->end_km === null) {
            throw ValidationException::withMessages([
                'rotation' => 'Les kilométrages de départ et d\'arrivée doivent être renseignés.',
            ]);
        }

        $distance = $rotation->end_km - $rotation->start_km;
        $truck = $rotation->truck;

        DB::transaction(function () use ($rotation, $validator, $distance, $truck) {
            // Lock the rotation
            $rotation->is_validated = true;
            $rotation->validated_at = now();
            $rotation->validated_by = $validator->id;
            $rotation->saveQuietly(); // bypass updating guard since we're setting is_validated

            // Add distance to truck mileage
            $this->kilometerService->addDistance(
                $truck,
                $distance,
                $rotation->client_date ?? $rotation->provider_date ?? Carbon::now(),
                "Rotation validée #{$rotation->reference}",
                'rotation'
            );

            // Recalculate maintenance profiles
            $this->maintenanceStatusService->recalculateForTruck($truck);
        });
    }

    public function getUnvalidatedRotations(?int $truckId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = TransportTracking::with(['truck', 'driver'])
            ->whereNotNull('start_km')
            ->whereNotNull('end_km')
            ->where('is_validated', false);

        if ($truckId) {
            $query->where('truck_id', $truckId);
        }

        return $query->orderByDesc('client_date')->get();
    }
}
