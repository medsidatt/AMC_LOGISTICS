<?php

namespace App\Http\Controllers;

use App\Models\EdkFuelTransaction;
use App\Models\FleetiDailyRecord;
use App\Models\FleetSetting;
use App\Models\FuelTracking;
use App\Services\Fuel\EdkFuelParser;
use App\Services\Fuel\FleetiFuelParser;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class FuelImportController extends Controller
{
    public function __construct(
        private readonly EdkFuelParser $edkParser,
        private readonly FleetiFuelParser $fleetiParser,
    ) {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            $user = auth()->user();
            abort_unless(
                $user && $user->hasAnyRole(['Admin', 'Super Admin']),
                403,
            );
            return $next($request);
        });
    }

    public function showPage()
    {
        $setting = FleetSetting::current();

        $recentEdk = EdkFuelTransaction::query()
            ->with(['truck:id,matricule', 'driver:id,name'])
            ->latest('occurred_at')
            ->take(15)
            ->get()
            ->map(fn (EdkFuelTransaction $r) => [
                'id' => $r->id,
                'date' => $r->occurred_at?->format('d/m/Y H:i'),
                'truck' => $r->truck?->matricule,
                'driver' => $r->driver?->name,
                'amount' => (float) $r->amount_fcfa,
                'litres' => round((float) $r->litres, 1),
                'transaction_id' => $r->transaction_id,
            ]);

        $recentFleeti = FleetiDailyRecord::query()
            ->with('truck:id,matricule')
            ->latest('record_date')
            ->take(15)
            ->get()
            ->map(fn (FleetiDailyRecord $r) => [
                'id' => $r->id,
                'date' => $r->record_date?->format('d/m/Y'),
                'truck' => $r->truck?->matricule,
                'kilometers' => (float) $r->kilometers,
                'consumed' => round((float) $r->consumed, 1),
                'refills_volume' => round((float) $r->refills_volume, 1),
                'refills_count' => (int) $r->refills_count,
            ]);

        $totals = [
            'edk_transactions' => EdkFuelTransaction::count(),
            'edk_litres' => round((float) EdkFuelTransaction::sum('litres'), 0),
            'edk_fcfa' => (float) EdkFuelTransaction::sum('amount_fcfa'),
            'fleeti_days' => FleetiDailyRecord::count(),
            'fleeti_litres' => round((float) FleetiDailyRecord::sum('consumed'), 0),
        ];

        return Inertia::render('fuel/Import', [
            'pricePerLitre' => (float) $setting->price_per_litre,
            'recentEdk' => $recentEdk,
            'recentFleeti' => $recentFleeti,
            'totals' => $totals,
        ]);
    }

    public function previewEdk(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:20480',
        ]);

        @ini_set('memory_limit', '512M');
        @set_time_limit(120);

        try {
            $contents = file_get_contents($request->file('file')->getRealPath());
            $setting = FleetSetting::current();
            $price = (float) ($request->input('price_per_litre') ?: $setting->price_per_litre);
            $preview = $this->edkParser->parse($contents, $price);
        } catch (\Throwable $e) {
            \Log::error('EDK import preview failed', [
                'error' => $e->getMessage(),
                'file' => $request->file('file')?->getClientOriginalName(),
            ]);
            return response()->json([
                'error' => 'Le fichier EDK n\'a pas pu être analysé : ' . $e->getMessage(),
                'valid' => [],
                'invalid' => [],
                'totals' => ['count_rows' => 0, 'litres' => 0, 'amount' => 0],
            ], 422);
        }

        $token = 'fuel-import:edk:' . auth()->id() . ':' . Str::random(16);
        Cache::put($token, $preview['valid'], now()->addHour());
        $preview['token'] = $token;

        return response()->json($preview);
    }

    public function commitEdk(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $token = $request->input('token');
        $rows = Cache::get($token);
        if (! is_array($rows) || empty($rows)) {
            return back()->with('error', 'Données expirées. Relance la prévisualisation.');
        }

        $setting = FleetSetting::current();
        $price = (float) $setting->price_per_litre;
        $userId = auth()->id();

        $inserted = 0;
        $skipped = 0;
        DB::transaction(function () use ($rows, $price, $userId, &$inserted, &$skipped) {
            foreach ($rows as $row) {
                $exists = EdkFuelTransaction::query()
                    ->where('transaction_id', $row['txn_id'])
                    ->where('truck_id', $row['truck_id'])
                    ->exists();
                if ($exists) {
                    $skipped++;
                    continue;
                }
                EdkFuelTransaction::create([
                    'truck_id' => $row['truck_id'],
                    'driver_id' => $row['driver_id'] ?? null,
                    'transaction_id' => $row['txn_id'],
                    'card_number' => $row['carte'] ?? null,
                    'holder_raw' => $row['porteur'] ?? null,
                    'amount_fcfa' => $row['montant'],
                    'litres' => $row['litres'],
                    'price_per_litre' => $price,
                    'occurred_at' => $row['date'],
                    'imported_by' => $userId,
                ]);
                $inserted++;
            }
        });

        Cache::forget($token);

        return back()->with('success', "EDK : {$inserted} transactions importées, {$skipped} doublons ignorés.");
    }

    public function previewFleeti(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:20480',
        ]);

        // Historical Fleeti exports can be large — give the parser breathing room.
        @ini_set('memory_limit', '1024M');
        @set_time_limit(180);

        try {
            $preview = $this->fleetiParser->parse($request->file('file')->getRealPath());
        } catch (\Throwable $e) {
            \Log::error('Fleeti import preview failed', [
                'error' => $e->getMessage(),
                'file' => $request->file('file')?->getClientOriginalName(),
            ]);
            return response()->json([
                'error' => 'Le fichier Fleeti n\'a pas pu être analysé : ' . $e->getMessage(),
                'valid' => [],
                'invalid' => [],
                'period' => ['from' => null, 'to' => null],
                'totals' => ['count_rows' => 0, 'count_trucks' => 0, 'litres_refilled' => 0, 'litres_consumed' => 0, 'km' => 0],
            ], 422);
        }

        $token = 'fuel-import:fleeti:' . auth()->id() . ':' . Str::random(16);
        Cache::put($token, $preview['valid'], now()->addHour());
        $preview['token'] = $token;

        return response()->json($preview);
    }

    public function commitFleeti(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $token = $request->input('token');
        $rows = Cache::get($token);
        if (! is_array($rows) || empty($rows)) {
            return back()->with('error', 'Données expirées. Relance la prévisualisation.');
        }

        $userId = auth()->id();

        $inserted = 0;
        $updated = 0;
        DB::transaction(function () use ($rows, $userId, &$inserted, &$updated) {
            foreach ($rows as $row) {
                $payload = [
                    'kilometers' => $row['kilometers'] ?? 0,
                    'volume_initial' => $row['volume_initial'] ?? 0,
                    'volume_final' => $row['volume_final'] ?? 0,
                    'consumed' => $row['consumed'] ?? 0,
                    'consumed_per_100km' => $row['consumed_per_100km'] ?? null,
                    'refills_count' => $row['refills_count'] ?? 0,
                    'refills_volume' => $row['refills_volume'] ?? 0,
                    'drains_count' => $row['drains_count'] ?? 0,
                    'drains_volume' => $row['drains_volume'] ?? 0,
                    'imported_by' => $userId,
                ];
                $existing = FleetiDailyRecord::query()
                    ->where('truck_id', $row['truck_id'])
                    ->where('record_date', $row['date'])
                    ->first();
                if ($existing) {
                    $existing->update($payload);
                    $updated++;
                } else {
                    FleetiDailyRecord::create(array_merge($payload, [
                        'truck_id' => $row['truck_id'],
                        'record_date' => $row['date'],
                    ]));
                    $inserted++;
                }
            }
        });

        Cache::forget($token);

        return back()->with('success', "Fleeti : {$inserted} jours ajoutés, {$updated} jours mis à jour.");
    }
}
