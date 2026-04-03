<?php

use App\Http\Controllers\API\FleetiSyncController;
use App\Http\Controllers\API\KilometerTrackingController;
use App\Http\Controllers\AuthController;
use App\Http\Resources\TransportTrackingExportResource;
use App\Models\TransportTracking;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // ------------------------
    // Public routes
    // ------------------------
    /**
     * @OA\Post(
     *     path="/api/v1/login",
     *     tags={"Auth"},
     *     summary="Login user and get token",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string"),
     *             @OA\Property(property="password", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Authenticated"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    Route::post('/login', [AuthController::class, 'login']);

    // ------------------------
    // Authenticated routes
    // ------------------------
    Route::middleware('auth:sanctum')->group(function () {

        // Logout
        /**
         * @OA\Post(
         *     path="/api/v1/logout",
         *     tags={"Auth"},
         *     summary="Logout user",
         *     security={{"sanctum": {}}},
         *     @OA\Response(response=200, description="Logged out")
         * )
         */
        Route::post('/logout', [AuthController::class, 'logout']);

        // Transport Trackings
        /**
         * @OA\Get(
         *   path="/api/v1/transport_trackings",
         *   tags={"Transport Tracking"},
         *   summary="List all transport trackings",
         *   security={{"sanctum": {}}},
         *   @OA\Response(
         *     response=200,
         *     description="OK",
         *     @OA\JsonContent(
         *         type="array",
         *         @OA\Items(ref="#/components/schemas/TransportTracking")
         *     )
         *   )
         * )
         */
        Route::get('/transport_tracking', function () {
            $trackings = TransportTracking::with(['truck', 'driver', 'provider', 'truck.transporter'])->get();

        return response()->json(

            $trackings->map(function ($tracking) {
                return [
                    'Id' => $tracking->id,
                    'Departure Date' => $tracking->client_date,
                    'Truck Number' => $tracking->truck?->matricule,
                    'Supplier' => $tracking->provider?->name,
                    'Product Type' => $tracking->product,
                    'Supplier Net Weight' => $tracking->provider_net_weight,
                    'Supplier Gross Weight' => $tracking->provider_gross_weight,
                    'Supplier Tare' => $tracking->provider_tare_weight,
                    'Delivery Date' => $tracking->client_date,
                    'Client Net Weight' => $tracking->client_net_weight,
                    'Client Gross Weight' => $tracking->client_gross_weight,
                    'Client Tare' => $tracking->client_tare_weight,
                    'Gap (C.Net - P.Net)' => $tracking->gap,
                    'Transporter' => $tracking->truck?->transporter?->name,
                    'Driver' => $tracking->driver?->name,
                    "is_gap_exceeded" => $tracking->gap > 5 ? 'Yes' : 'No'
                ];
            })
        );

        });

        // Dropdown routes

        /**
         * @OA\Get(
         *     path="/api/v1/trucks",
         *     tags={"Dropdowns"},
         *     summary="List all trucks (simple list)",
         *     security={{"sanctum": {}}},
         *     @OA\Response(
         *         response=200,
         *         description="OK",
         *         @OA\JsonContent(
         *             type="array",
         *             @OA\Items(
         *                 @OA\Property(property="id", type="integer"),
         *                 @OA\Property(property="matricule", type="string")
         *             )
         *         )
         *     )
         * )
         */
        Route::get('/trucks', function () {
            $trucks = \App\Models\Truck::select('id', 'matricule')->get();
            return response()->json($trucks);
        });

        /**
         * @OA\Get(
         *     path="/api/v1/drivers",
         *     tags={"Dropdowns"},
         *     summary="List all drivers (simple list)",
         *     security={{"sanctum": {}}},
         *     @OA\Response(
         *         response=200,
         *         description="OK",
         *         @OA\JsonContent(
         *             type="array",
         *             @OA\Items(
         *                 @OA\Property(property="id", type="integer"),
         *                 @OA\Property(property="name", type="string")
         *             )
         *         )
         *     )
         * )
         */
        Route::get('/drivers', function () {
            $drivers = \App\Models\Driver::select('id', 'name')->get();
            return response()->json($drivers);
        });

        /**
         * @OA\Get(
         *     path="/api/v1/providers",
         *     tags={"Dropdowns"},
         *     summary="List all providers (simple list)",
         *     security={{"sanctum": {}}},
         *     @OA\Response(
         *         response=200,
         *         description="OK",
         *         @OA\JsonContent(
         *             type="array",
         *             @OA\Items(
         *                 @OA\Property(property="id", type="integer"),
         *                 @OA\Property(property="name", type="string")
         *             )
         *         )
         *     )
         * )
         */
        Route::get('/providers', function () {
            $providers = \App\Models\Provider::select('id', 'name')->get();
            return response()->json($providers);
        });

        /**
         * @OA\Get(
         *     path="/api/v1/transporters",
         *     tags={"Dropdowns"},
         *     summary="List all transporters (simple list)",
         *     security={{"sanctum": {}}},
         *     @OA\Response(
         *         response=200,
         *         description="OK",
         *         @OA\JsonContent(
         *             type="array",
         *             @OA\Items(
         *                 @OA\Property(property="id", type="integer"),
         *                 @OA\Property(property="name", type="string")
         *             )
         *         )
         *     )
         * )
         */
        Route::get('/transporters', function () {
            $transporters = \App\Models\Transporter::select('id', 'name')->get();
            return response()->json($transporters);
        });

        // Logistics API Resources
        Route::apiResource('kilometer-trackings', KilometerTrackingController::class)->only(['store']);
        Route::post('fleeti/sync-kilometers', [FleetiSyncController::class, 'syncKilometers'])->name('api.fleeti.sync-kilometers');
        Route::post('trucks/{truck}/update-maintenance-type', [\App\Http\Controllers\TruckController::class, 'updateMaintenanceType'])->name('api.trucks.update-maintenance-type');
        Route::post('trucks/bulk-update-maintenance-type', [\App\Http\Controllers\TruckController::class, 'bulkUpdateMaintenanceType'])->name('api.trucks.bulk-update-maintenance-type');
    });
});
