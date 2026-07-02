<?php

namespace App\Http\Controllers;

use App\Domain\Analytics\Fuel\FuelDashboardDataProvider;
use App\Models\Driver;
use App\Models\FleetiDailyRecord;
use App\Models\FleetSetting;
use App\Models\FuelCardTransaction;
use App\Models\Truck;
use App\Services\Fuel\FleetiFuelParser;
use App\Services\Fuel\FleetiImportService;
use App\Services\Fuel\FuelImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class FuelImportController extends Controller
{
    public function __construct(
        private readonly FuelImportService $fuelImportService,
        private readonly FleetiFuelParser $fleetiParser,
        private readonly FleetiImportService $fleetiImportService,
    ) {
        $this->middleware('auth');
        $this->middleware('permission:fuel-import');
    }

    /**
     * Descriptive fuel analytics payload for dashboards. Thin boundary: validates the period and
     * returns exactly what FuelDashboardDataProvider composed — no query, no shaping, no KPI.
     */
    public function analytics(Request $request, FuelDashboardDataProvider $provider)
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        // Default period: current calendar year to date (descriptive default, not a business rule).
        $from = new \DateTimeImmutable(($validated['from'] ?? now()->startOfYear()->toDateString()).' 00:00:00');
        $to = new \DateTimeImmutable(($validated['to'] ?? now()->toDateString()).' 23:59:59');

        return response()->json($provider->dashboard($from, $to));
    }

    /** Fuel workspace — server-paginated EDK or Fleeti records (tabbed). */
    public function index(Request $request)
    {
        $tab = $request->query('tab') === 'fleeti' ? 'fleeti' : 'edk';
        $filters = array_filter($request->only(['truck_id', 'driver_id', 'start_date', 'end_date']));

        $records = $tab === 'edk'
            ? $this->edkQuery($filters)->latest('occurred_at')->paginate(15)->through(fn (FuelCardTransaction $r) => [
                'id' => $r->id,
                'date' => $r->occurred_at?->format('d/m/Y H:i'),
                'truck' => $r->truck?->matricule,
                'driver' => $r->driver?->name,
                'amount' => (float) $r->amount_fcfa,
                'litres' => round((float) $r->estimated_litres, 1),
                'transaction_id' => $r->transaction_ref,
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
                'edk_recharges' => FuelCardTransaction::count(),
                'edk_estimated_litres' => round((float) FuelCardTransaction::sum('estimated_litres'), 0),
                'edk_fcfa' => (float) FuelCardTransaction::sum('amount_fcfa'),
                'fleeti_days' => FleetiDailyRecord::count(),
                'fleeti_litres' => round((float) FleetiDailyRecord::sum('consumed'), 0),
            ],
            'pricePerLitre' => (float) FleetSetting::current()->price_per_litre,
        ]);
    }

    private function edkQuery(array $filters)
    {
        return FuelCardTransaction::query()
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

    /** EDK recharge details + matching Fleeti consumption (validation) + truck history. */
    public function showEdk(FuelCardTransaction $recharge)
    {
        $recharge->load(['truck:id,matricule', 'driver:id,name', 'importedBy:id,name']);
        $date = $recharge->occurred_at?->toDateString();

        $fleeti = ($recharge->truck_id && $date)
            ? FleetiDailyRecord::where('truck_id', $recharge->truck_id)->whereDate('record_date', $date)->first()
            : null;

        return response()->json([
            'record' => [
                'id' => $recharge->id,
                'date' => $recharge->occurred_at?->format('d/m/Y H:i'),
                'truck' => $recharge->truck?->matricule,
                'driver' => $recharge->driver?->name,
                'amount' => (float) $recharge->amount_fcfa,
                'litres' => round((float) $recharge->estimated_litres, 1),
                'price_per_litre' => (float) $recharge->price_per_litre,
                'transaction_id' => $recharge->transaction_ref,
                'imported_by' => $recharge->importedBy?->name,
                'imported_at' => $recharge->created_at?->format('d/m/Y H:i'),
            ],
            'validation' => $fleeti ? [
                'date' => $fleeti->record_date?->format('d/m/Y'),
                'kilometers' => (float) $fleeti->kilometers,
                'consumed' => round((float) $fleeti->consumed, 1),
                'consumed_per_100km' => $fleeti->consumed_per_100km !== null ? round((float) $fleeti->consumed_per_100km, 1) : null,
                'refills_volume' => round((float) $fleeti->refills_volume, 1),
            ] : null,
            'history' => FuelCardTransaction::where('truck_id', $recharge->truck_id)->where('id', '!=', $recharge->id)
                ->latest('occurred_at')->take(8)->get()
                ->map(fn ($r) => ['id' => $r->id, 'date' => $r->occurred_at?->format('d/m/Y'), 'amount' => (float) $r->amount_fcfa, 'litres' => round((float) $r->estimated_litres, 1)])->all(),
        ]);
    }

    /** Fleeti daily record details + matching EDK recharges (validation) + truck history. */
    public function showFleeti(FleetiDailyRecord $record)
    {
        $record->load(['truck:id,matricule', 'importedBy:id,name']);
        $date = $record->record_date?->toDateString();

        $edk = ($record->truck_id && $date)
            ? FuelCardTransaction::with('driver:id,name')->where('truck_id', $record->truck_id)->whereDate('occurred_at', $date)->get()
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
                'litres' => round((float) $edk->sum('estimated_litres'), 1),
                'amount' => (float) $edk->sum('amount_fcfa'),
                'transactions' => $edk->map(fn ($r) => ['id' => $r->id, 'driver' => $r->driver?->name, 'amount' => (float) $r->amount_fcfa, 'litres' => round((float) $r->estimated_litres, 1)])->all(),
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
                fputcsv($out, ['Date', 'Camion', 'Chauffeur', 'Montant FCFA', 'Litres estimes', 'Transaction']);
                $this->edkQuery($filters)->latest('occurred_at')->chunk(500, function ($rows) use ($out) {
                    foreach ($rows as $r) {
                        fputcsv($out, [$r->occurred_at?->format('Y-m-d H:i'), $r->truck?->matricule, $r->driver?->name, $r->amount_fcfa, $r->estimated_litres, $r->transaction_ref]);
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
            $price = (float) ($request->input('price_per_litre') ?: FleetSetting::current()->price_per_litre);
            $preview = $this->fuelImportService->preview($contents, $price);
        } catch (\Throwable $e) {
            Log::error('EDK import preview failed', [
                'error' => $e->getMessage(),
                'file' => $request->file('file')?->getClientOriginalName(),
            ]);
            return response()->json([
                'error' => 'Le fichier EDK n\'a pas pu être analysé : ' . $e->getMessage(),
                'summary' => ['accepted_rows' => 0, 'rejected_rows' => 0],
                'rows' => [],
            ], 422);
        }

        // Cache the raw file so commit re-runs the authoritative pipeline (single source of persistence).
        $token = 'fuel-import:edk:' . auth()->id() . ':' . Str::random(16);
        Cache::put($token, [
            'contents' => $contents,
            'price' => $price,
            'filename' => $request->file('file')->getClientOriginalName(),
        ], now()->addHour());

        return response()->json($preview + ['price_per_litre' => $price, 'token' => $token]);
    }

    public function commitEdk(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $token = $request->input('token');
        $cached = Cache::get($token);
        if (! is_array($cached) || empty($cached['contents'])) {
            return back()->with('error', 'Données expirées. Relance la prévisualisation.');
        }

        // Thin: the entire pipeline + persistence lives in FuelImportService (the sole owner).
        $batch = $this->fuelImportService->import(
            $cached['contents'],
            (float) ($cached['price'] ?? FleetSetting::current()->price_per_litre),
            $cached['filename'] ?? null,
            auth()->id(),
        );

        Cache::forget($token);

        return back()->with('success', "EDK : {$batch->accepted_rows} acceptée(s), {$batch->rejected_rows} rejetée(s).");
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

        // Persistence (upsert by truck+date, source-owned columns) is owned by FleetiImportService
        // so the HTTP commit and the historical CLI importer share exactly one path.
        $counts = $this->fleetiImportService->persist($rows, auth()->id());

        Cache::forget($token);

        return back()->with('success', "Fleeti : {$counts['inserted']} jours ajoutés, {$counts['updated']} jours mis à jour.");
    }
}
