<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Repositories\TruckRepository;
use App\Services\KilometerService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class KilometerTrackingController extends Controller
{
    public function __construct(
        private readonly TruckRepository $truckRepository,
        private readonly KilometerService $kilometerService
    ) {
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'matricule' => 'required|string|exists:trucks,matricule',
            'kilometers' => 'required|numeric',
            'date' => 'required|date',
            'notes' => 'nullable|string',
            'reading_type' => 'nullable|string|in:delta,absolute',
        ]);

        $truck = $this->truckRepository->findByMatriculeOrFail($data['matricule']);
        $date = now()->parse($data['date']);
        $readingType = $data['reading_type'] ?? 'delta';

        try {
            if ($readingType === 'absolute') {
                $result = $this->kilometerService->applyExternalOdometerReading(
                    $truck,
                    (float) $data['kilometers'],
                    $date,
                    'manual-api'
                );

                return response()->json([
                    'message' => 'Absolute odometer reading processed successfully.',
                    'data' => $result,
                    'total_kilometers' => $truck->fresh()->total_kilometers,
                ], 201);
            }

            $kilometerTracking = $this->kilometerService->addDistance(
                $truck,
                (float) $data['kilometers'],
                $date,
                $data['notes'] ?? null,
                'manual-api'
            );

            return response()->json([
                'message' => 'Kilometer tracking data stored successfully.',
                'data' => $kilometerTracking,
                'total_kilometers' => $truck->fresh()->total_kilometers,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }
}
