<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\FleetiSyncService;
use Illuminate\Http\Request;

class FleetiSyncController extends Controller
{
    public function __construct(private readonly FleetiSyncService $fleetiSyncService)
    {
    }

    public function syncKilometers(Request $request)
    {
        $data = $request->validate([
            'customer_reference' => 'nullable|string|max:100',
            'force_all' => 'nullable|boolean',
        ]);

        $summary = $this->fleetiSyncService->syncKilometers(
            $data['customer_reference'] ?? null,
            ! ($data['force_all'] ?? false)
        );

        return response()->json([
            'message' => 'Fleeti kilometers synchronized successfully.',
            'data' => $summary,
        ]);
    }
}
