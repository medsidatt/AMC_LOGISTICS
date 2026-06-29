<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\EdkFuelTransaction;
use App\Models\FleetiDailyRecord;
use App\Models\FleetSetting;
use App\Models\FuelTracking;
use App\Models\Truck;
use App\Services\Fuel\EdkFuelParser;
use App\Services\Fuel\FleetiFuelParser;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class FuelImportController extends Controller
{
    public function __construct(
        private readonly EdkFuelParser $edkParser,
        private readonly FleetiFuelParser $fleetiParser,
    ) {
        $this->middleware('auth');
        $this->middleware('permission:fuel-import');
    }

    /** Fuel workspace — server-paginated EDK or Fleeti records (tabbed). */
    public function index(Request $request)
    {
        $tab = $request->query('tab') === 'fleeti' ? 'fleeti' : 'edk';
        $filters = array_filter($request->only(['truck_id', 'driver_id', 'start_date', 'end_date']));

        $records = $tab === 'edk'
            ? $this->edkQuery($filters)->latest('occurred_at')->paginate(15)->through(fn (EdkFuelTransaction $r) => [
                'id' => $r->id,
                'date' => $r->occurred_at?->format('d/m/Y H:i'),
                'truck' => $r->truck?->matricule,
                'driver' => $r->driver?->name,
                'amount' => (float) $r->amount_fcfa,
                'litres' => round((float) $r->litres, 1),
                'transaction_id' => $r->transaction_id,
            ])->appends($request->query())
            : $this->fleetiQuery($filters)->latest('record_date')->paginate(15)->through(fn (FleetiDailyRecord $r) => [
                'id' => $r->id,
                'date' => $r->record_date?->format('d/m/Y'),
                'truck' => $r->truck?->matricule,
                'kilometers' => (float) $r->kilometers,
                'consumed' => round((float) $r->consumed, 1),
                'consumed_per_100km' => $r->consumed_per_100km !== null ? round((float) $r->consumed_per_100km, 1) : null,
                'refills_volume' => round((float) $r->refills_volume, 1),
                'refills_count' => (int) $r->refills_count,
            ])->appends($request->query());

        return Inertia::render('fuel/Index', [
            'tab' => $tab,
            'records' => $records,
            'filters' => $filters,
            'trucks' => Truck::where('is_active', true)->orderBy('matricule')->get(['id', 'matricule'])->map(fn ($t) => ['id' => $t->id, 'name' => $t->matricule])->all(),
            'drivers' => Driver::where('is_active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->all(),
            'totals' => [
                'edk_transactions' => EdkFuelTransaction::count(),
                'edk_litres' => round((float) EdkFuelTransaction::sum('litres'), 0),
                'edk_fcfa' => (float) EdkFuelTransaction::sum('amount_fcfa'),
                'fleeti_days' => FleetiDailyRecord::count(),
                'fleeti_litres' => round((float) FleetiDailyRecord::sum('consumed'), 0),
            ],
            'pricePerLitre' => (float) FleetSetting::current()->price_per_litre,
        ]);
    }

    private function edkQuery(array $filters)
    {
        return EdkFuelTransaction::query()
            ->with(['truck:id,matricule', 'driver:id,name'])
            ->when($filters['truck_id'] ?? null, fn ($q, $v) => $q->where('truck_id', $v))
            ->when($filters['driver_id'] ?? null, fn ($q, $v) => $q->where('driver_id', $v))
            ->when($filters['start_date'] ?? null, fn ($q, $v) => $q->whereDate('occurred_at', '>=', $v))
            ->when($filters['end_date'] ?? null, fn ($q, $v) => $q->whereDate('occurred_at', '<=', $v));
    }

    private function fleetiQuery(array $filters)
    {
        return FleetiDailyRecord::query()
            ->with('truck:id,matricule')
            ->when($filters['truck_id'] ?? null, fn ($q, $v) => $q->where('truck_id', $v))
            ->when($filters['start_date'] ?? null, fn ($q, $v) => $q->whereDate('record_date', '>=', $v))
            ->when($filters['end_date'] ?? null, fn ($q, $v) => $q->whereDate('record_date', '<=', $v));
    }

    /** EDK transaction details + matching Fleeti consumption (validation) + truck history. */
    public function showEdk(EdkFuelTransaction $transaction)
    {
        $transaction->load(['truck:id,matricule', 'driver:id,name', 'importedBy:id,name']);
        $date = $transaction->occurred_at?->toDateString();

        $fleeti = ($transaction->truck_id && $date)
            ? FleetiDailyRecord::where('truck_id', $transaction->truck_id)->whereDate('record_date', $date)->first()
            : null;

        return response()->json([
            'record' => [
                'id' => $transaction->id,
                'date' => $transaction->occurred_at?->format('d/m/Y H:i'),
                'truck' => $transaction->truck?->matricule,
                'driver' => $transaction->driver?->name,
                'amount' => (float) $transaction->amount_fcfa,
                'litres' => round((float) $transaction->litres, 1),
                'price_per_litre' => (float) $transaction->price_per_litre,
                'transaction_id' => $transaction->transaction_id,
                'imported_by' => $transaction->importedBy?->name,
                'imported_at' => $transaction->created_at?->format('d/m/Y H:i'),
            ],
            'validation' => $fleeti ? [
                'date' => $fleeti->record_date?->format('d/m/Y'),
                'kilometers' => (float) $fleeti->kilometers,
                'consumed' => round((float) $fleeti->consumed, 1),
                'consumed_per_100km' => $fleeti->consumed_per_100km !== null ? round((float) $fleeti->consumed_per_100km, 1) : null,
                'refills_volume' => round((float) $fleeti->refills_volume, 1),
            ] : null,
            'history' => EdkFuelTransaction::where('truck_id', $transaction->truck_id)->where('id', '!=', $transaction->id)
                ->latest('occurred_at')->take(8)->get()
                ->map(fn ($r) => ['id' => $r->id, 'date' => $r->occurred_at?->format('d/m/Y'), 'amount' => (float) $r->amount_fcfa, 'litres' => round((float) $r->litres, 1)])->all(),
        ]);
    }

    /** Fleeti daily record details + matching EDK purchases (validation) + truck history. */
    public function showFleeti(FleetiDailyRecord $record)
    {
        $record->load(['truck:id,matricule', 'importedBy:id,name']);
        $date = $record->record_date?->toDateString();

        $edk = ($record->truck_id && $date)
            ? EdkFuelTransaction::with('driver:id,name')->where('truck_id', $record->truck_id)->whereDate('occurred_at', $date)->get()
            : collect();

        return response()->json([
            'record' => [
                'id' => $record->id,
                'date' => $record->record_date?->format('d/m/Y'),
                'truck' => $record->truck?->matricule,
                'kilometers' => (float) $record->kilometers,
                'volume_initial' => round((float) $record->volume_initial, 1),
                'volume_final' => round((float) $record->volume_final, 1),
                'consumed' => round((float) $record->consumed, 1),
                'consumed_per_100km' => $record->consumed_per_100km !== null ? round((float) $record->consumed_per_100km, 1) : null,
                'refills_volume' => round((float) $record->refills_volume, 1),
                'refills_count' => (int) $record->refills_count,
                'drains_volume' => round((float) $record->drains_volume, 1),
                'drains_count' => (int) $record->drains_count,
                'imported_by' => $record->importedBy?->name,
                'imported_at' => $record->created_at?->format('d/m/Y H:i'),
            ],
            'validation' => $edk->isNotEmpty() ? [
                'count' => $edk->count(),
                'litres' => round((float) $edk->sum('litres'), 1),
                'amount' => (float) $edk->sum('amount_fcfa'),
                'transactions' => $edk->map(fn ($r) => ['id' => $r->id, 'driver' => $r->driver?->name, 'amount' => (float) $r->amount_fcfa, 'litres' => round((float) $r->litres, 1)])->all(),
            ] : null,
            'history' => FleetiDailyRecord::where('truck_id', $record->truck_id)->where('id', '!=', $record->id)
                ->latest('record_date')->take(8)->get()
                ->map(fn ($r) => ['id' => $r->id, 'date' => $r->record_date?->format('d/m/Y'), 'consumed' => round((float) $r->consumed, 1), 'kilometers' => (float) $r->kilometers])->all(),
        ]);
    }

    /** Export the current tab's filtered records to CSV. */
    public function export(Request $request)
    {
        $tab = $request->query('tab') === 'fleeti' ? 'fleeti' : 'edk';
        $filters = array_filter($request->only(['truck_id', 'driver_id', 'start_date', 'end_date']));
        $name = 'carburant-' . $tab . '-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($tab, $filters) {
            $out = fopen('php://output', 'w');
            if ($tab === 'edk') {
                fputcsv($out, ['Date', 'Camion', 'Chauffeur', 'Montant FCFA', 'Litres', 'Transaction']);
                $this->edkQuery($filters)->latest('occurred_at')->chunk(500, function ($rows) use ($out) {
                    foreach ($rows as $r) {
                        fputcsv($out, [$r->occurred_at?->format('Y-m-d H:i'), $r->truck?->matricule, $r->driver?->name, $r->amount_fcfa, $r->litres, $r->transaction_id]);
                    }
                });
            } else {
                fputcsv($out, ['Date', 'Camion', 'Km', 'Consomme L', 'L/100km', 'Remplis L', 'Remplissages']);
                $this->fleetiQuery($filters)->latest('record_date')->chunk(500, function ($rows) use ($out) {
                    foreach ($rows as $r) {
                        fputcsv($out, [$r->record_date?->format('Y-m-d'), $r->truck?->matricule, $r->kilometers, $r->consumed, $r->consumed_per_100km, $r->refills_volume, $r->refills_count]);
                    }
                });
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
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
            Log::error('EDK import preview failed', [
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
            $uploaded = $request->file('file');
            $ext = strtolower((string) $uploaded->getClientOriginalExtension());
            $readerType = $ext === 'xls'
                ? \Maatwebsite\Excel\Excel::XLS
                : \Maatwebsite\Excel\Excel::XLSX;
            $preview = $this->fleetiParser->parse($uploaded->getRealPath(), $readerType);
        } catch (\Throwable $e) {
            Log::error('Fleeti import preview failed', [
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
