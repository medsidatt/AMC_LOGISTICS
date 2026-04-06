<?php

namespace App\Http\Controllers;

use App\Exports\MissingTransportTrackingExport;
use App\Exports\TransportTrackingExport;
use App\Imports\TransportTrackingImport;
use App\Models\Document;
use App\Models\Driver;
use App\Models\Provider;
use App\Models\Transporter;
use App\Models\TransportTracking;
use App\Models\Truck;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use OpenAI\Laravel\Facades\OpenAI;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;
use setasign\Fpdi\PdfParser\Filter\FilterException;
use setasign\Fpdi\PdfParser\PdfParserException;
use setasign\Fpdi\PdfParser\Type\PdfTypeException;
use setasign\Fpdi\PdfReader\PdfReaderException;
use Symfony\Component\HttpFoundation\StreamedResponse;


class TransportTrackingController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:transport-tracking-list', ['only' => ['index', 'show', 'showPage', 'dashboard']]);
        $this->middleware('permission:transport-tracking-create', ['only' => ['create', 'createPage', 'store', 'import']]);
        $this->middleware('permission:transport-tracking-edit', ['only' => ['edit', 'editPage', 'update']]);
        $this->middleware('permission:transport-tracking-delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $transportTrackings = TransportTracking::query();

        $filters = $request->filters;

        foreach ([
                     'transporter_id_filter',
                     'truck_id_filter',
                     'driver_id_filter',
                     'provider_id_filter'
                 ] as $filter) {
            if (isset($filters[$filter]) && $filters[$filter] !== '') {
                if ($filter === 'transporter_id_filter') {
                    $transportTrackings->whereHas('truck', function ($query) use ($filters, $filter) {
                        $query->where('transporter_id', $filters[$filter]);
                    });
                    continue;
                }
                $column = str_replace('_filter', '', $filter);
                $transportTrackings->where($column, $filters[$filter]);
            }
        }

        if (isset($filters['start_date']) && $filters['start_date'] !== '') {
            $transportTrackings->whereDate('client_date', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date']) && $filters['end_date'] !== '') {
            $transportTrackings->whereDate('client_date', '<=', $filters['end_date']);
        }

        // Keep AJAX branch for mobile API (not Inertia)
        if ($request->ajax() && !$request->header('X-Inertia')) {
            return datatables()
                ->of($transportTrackings)
                ->editColumn('reference', fn ($t) => $t->reference)
                ->editColumn('client_date', fn ($t) => $t->client_date)
                ->editColumn('provider_net_weight', fn ($t) => $t->provider_net_weight)
                ->editColumn('client_net_weight', fn ($t) => $t->client_net_weight)
                ->editColumn('gap', fn ($t) => $t->gap)
                ->addColumn('actions', fn ($t) => '')
                ->make(true);
        }

        $trackings = $transportTrackings
            ->with(['truck', 'driver', 'provider', 'documents'])
            ->orderByDesc('client_date')
            ->paginate(15)
            ->through(fn (TransportTracking $t) => [
                'id' => $t->id,
                'reference' => $t->reference,
                'product' => $t->product,
                'base' => $t->base,
                'provider_date' => $t->provider_date?->format('d/m/Y'),
                'client_date' => $t->client_date?->format('d/m/Y'),
                'provider_net_weight' => $t->provider_net_weight,
                'client_net_weight' => $t->client_net_weight,
                'gap' => $t->gap,
                'has_files' => $t->documents->whereIn('mime_type', ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'])->isNotEmpty(),
                'truck' => $t->truck ? ['id' => $t->truck->id, 'matricule' => $t->truck->matricule] : null,
                'driver' => $t->driver ? ['id' => $t->driver->id, 'name' => $t->driver->name] : null,
                'provider' => $t->provider ? ['id' => $t->provider->id, 'name' => $t->provider->name] : null,
            ]);

        return Inertia::render('transport-trackings/Index', [
            'trackings' => $trackings,
            'filters' => $filters ?? [],
            'transporters' => Transporter::all()->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->toArray(),
            'trucks' => Truck::all()->map(fn ($t) => ['id' => $t->id, 'matricule' => $t->matricule])->toArray(),
            'drivers' => Driver::all()->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->toArray(),
            'providers' => Provider::all()->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->toArray(),
            'products' => [
                ['id' => '0/3', 'name' => '0/3'],
                ['id' => '3/8', 'name' => '3/8'],
                ['id' => '8/16', 'name' => '8/16'],
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return $this->createPage();
    }

    public function createPage()
    {
        $transporters = Transporter::all();
        $trucks = Truck::get()
            ->map(function ($truck) {
                return [
                    'id' => $truck->id,
                    'matricule' => $truck->matricule,
                    'driver_id' => $truck->transportTrackings->first()?->driver_id,
                    'transporter_id' => $truck?->transporter_id,
                ];
            });
        $drivers = Driver::all();
        $providers = Provider::all();
        $products = collect(['0/3', '3/8', '8/16'])->map(function ($product) {
            return [
                'id' => $product,
                'name' => $product
            ];
        });
        $bases = collect([
            ['id' => 'mr', 'name' => 'Mauritanie'],
            ['id' => 'sn', 'name' => 'Sénégal'],
            ['id' => 'none', 'name' => 'Non definie'],
        ]);

        return Inertia::render('transport-trackings/Create', [
            'transporters' => $transporters->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->toArray(),
            'trucks' => $trucks->toArray(),
            'drivers' => $drivers->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->toArray(),
            'providers' => $providers->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->toArray(),
            'products' => $products->toArray(),
            'bases' => $bases->toArray(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'truck_id' => 'required|string',
            'driver_id' => 'required|string',
            'provider_id' => 'nullable|exists:providers,id',

            'product' => 'required|in:0/3,3/8,8/16',
            'base' => 'required|in:mr,sn,none',

            'provider_date' => 'nullable|date',
            'client_date' => 'nullable|date',
            'commune_date' => 'nullable|date',
            'commune_weight' => 'nullable|numeric',

            'files.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:4096',

            'provider_gross_weight' => 'nullable|numeric',
            'provider_tare_weight' => 'nullable|numeric',
            'provider_net_weight' => 'nullable|numeric',

            'client_gross_weight' => 'nullable|numeric',
            'client_tare_weight' => 'nullable|numeric',
            'client_net_weight' => 'nullable|numeric',
        ]);

        /** ---------------- Resolve Truck ---------------- */
        $truckInput = $validated['truck_id'];

        $truck = is_numeric($truckInput)
            ? Truck::find($truckInput)
            : Truck::where('matricule', $truckInput)->first();

        if (!$truck) {
            $truck = Truck::create([
                'matricule' => $truckInput,
                'transporter_id' => $request->input('transporter_id'),
            ]);
        }

        /** ---------------- Resolve Driver ---------------- */
        $driverInput = $validated['driver_id'];

        $driver = is_numeric($driverInput)
            ? Driver::find($driverInput)
            : Driver::where('name', $driverInput)->first();

        if (!$driver) {
            $driver = Driver::create(['name' => $driverInput]);
        }

        /** ---------------- Prepare Data ---------------- */
        $data = collect($validated)
            ->except(['files', 'truck_id', 'driver_id'])
            ->merge([
                'truck_id' => $truck->id,
                'driver_id' => $driver->id,
            ])
            ->toArray();

        /** ---------------- Prevent Duplicate ---------------- */
        $query = TransportTracking::where('truck_id', $truck->id);

        if (!empty($data['client_date'])) {
            $query->whereDate('client_date', $data['client_date']);
        }

        $record = $query->first();

        if ($record) {
            $record->update(array_filter($data));
        } else {
            $record = TransportTracking::create($data);

            // Increment total_rotations counter
            // Note: rotations_since_maintenance is now calculated dynamically from transport_trackings
            $truck->increment('total_rotations');
        }

        /** ---------------- Files Upload ---------------- */
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('transport_trackings', 'public');

                // Determine file type from filename or default to 'other'
                $originalName = strtolower($file->getClientOriginalName());
                $type = 'other';
                if (strpos($originalName, 'provider') !== false || strpos($originalName, 'fournisseur') !== false) {
                    $type = 'provider';
                } elseif (strpos($originalName, 'client') !== false) {
                    $type = 'client';
                } elseif (strpos($originalName, 'commune') !== false) {
                    $type = 'commune';
                }

                Document::create([
                    'transport_tracking_id' => $record->id,
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'type' => $type,
                ]);
            }
        }

        return redirect()
            ->route('transport_tracking.index')
            ->with('success', 'Stock saved successfully');
    }


    public function import(Request $request)
    {
        if ($request->ajax() && $request->isMethod('POST')) {
            $request->validate([
                'stock_file' => 'required|file|mimes:xlsx,xls,csv'
            ]);
            $file = $request->file('stock_file');
            Excel::import(
                new TransportTrackingImport,
                $file,
                null,
                \Maatwebsite\Excel\Excel::XLSX,
            );

            return back()->with('success', 'Transport tracking data imported successfully!');
        }

        return view("pages.transport_trackings.import");
    }

    /**
     * Preview all files (PDFs + images) inline in a modal.
     */
    public function previewFiles($id)
    {
        $tracking = TransportTracking::with('documents')->findOrFail($id);
        $documents = $tracking->documents()
            ->whereIn('mime_type', ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'])
            ->orderBy('type')
            ->orderBy('created_at')
            ->get();


        return view('pages.transport_trackings.preview-files', [
            'tracking' => $tracking,
            'documents' => $documents,
        ]);
    }

    public function openCombinedPDF($id)
    {
        $tracking = TransportTracking::with('documents')->findOrFail($id);

        // Get PDF documents (filter by PDF mime type)
        $documents = $tracking->documents()
            ->where('mime_type', 'application/pdf')
            ->orderBy('type')
            ->orderBy('created_at')
            ->get();

        if ($documents->isEmpty()) {
            return response()->json(['error' => 'No PDF files found'], 404);
        }

        // If only one PDF, stream it directly
        if ($documents->count() === 1) {
            $document = $documents->first();
            $path = storage_path('app/public/' . $document->file_path);

            if (!is_file($path)) {
                return response()->json(['error' => 'File not found'], 404);
            }

            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $document->original_name . '"');
            readfile($path);
            exit;
        }

        // Merge multiple PDFs with proper page sizes
        $pdf = new Fpdi();
        $pagesAdded = false;

        foreach ($documents as $document) {
            $filePath = storage_path('app/public/' . $document->file_path);

            if (!is_file($filePath)) {
                continue; // Skip missing files
            }

            try {
                $pageCount = $pdf->setSourceFile($filePath);

                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    // Import page and get its size
                    $templateId = $pdf->importPage($pageNo);
                    $size = $pdf->getTemplateSize($templateId);

                    // Determine page orientation and dimensions
                    $width = $size['width'];
                    $height = $size['height'];
                    $orientation = ($width > $height) ? 'L' : 'P'; // Landscape or Portrait

                    // Add page with correct size
                    $pdf->AddPage($orientation, [$width, $height]);
                    $pdf->useTemplate($templateId, 0, 0, $width, $height, true);
                    $pagesAdded = true;
                }
            } catch (\Exception $e) {
                // Skip problematic PDFs and continue
                continue;
            }
        }

        // Check if any pages were added
        if (!$pagesAdded) {
            return response()->json(['error' => 'No valid PDF pages could be processed'], 404);
        }

        // Stream merged PDF directly
        $pdf->Output('I', 'merged_' . $tracking->reference . '.pdf'); // "I" = inline
        exit;
    }

    public function filePage($id)
    {
        $tracking = TransportTracking::with('documents')->findOrFail($id);
        $documents = $tracking->documents()
            ->whereIn('mime_type', ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'])
            ->orderBy('type')
            ->orderBy('created_at')
            ->get();

        if ($documents->isEmpty()) {
            abort(404, 'No documents found for this tracking.');
        }

        $documentTitle = 'Documents - ' . $tracking->reference;
        $fileUrl = route('transport_tracking.preview-files', $tracking->id);

        if ($documents->count() === 1) {
            $document = $documents->first();
            $documentTitle = $document->original_name ?? basename($document->file_path);
            $fileUrl = $document->mime_type === 'application/pdf'
                ? route('transport_tracking.file', $tracking->id)
                : Storage::url($document->file_path);
        } else {
            $pdfCount = $documents->where('mime_type', 'application/pdf')->count();
            if ($pdfCount > 0) {
                $documentTitle = 'merged_' . $tracking->reference . '.pdf';
                $fileUrl = route('transport_tracking.file', $tracking->id);
            }
        }

        return view('pages.transport_trackings.file-page', [
            'documentTitle' => $documentTitle,
            'fileUrl' => $fileUrl,
        ]);
    }


    /**
     * Display the specified resource.
     */
    public function show(TransportTracking $transportTracking)
    {
        return $this->showPage($transportTracking);
    }

    public function showPage(TransportTracking $transportTracking)
    {
        $transportTracking->load(['documents', 'truck', 'driver', 'provider']);

        return Inertia::render('transport-trackings/Show', [
            'tracking' => [
                'id' => $transportTracking->id,
                'reference' => $transportTracking->reference,
                'truck' => $transportTracking->truck?->matricule,
                'driver' => $transportTracking->driver?->name,
                'provider' => $transportTracking->provider?->name,
                'transporter' => $transportTracking->truck?->transporter?->name ?? null,
                'product' => $transportTracking->product,
                'base' => $transportTracking->base,
                'provider_date' => $transportTracking->provider_date?->format('d/m/Y'),
                'client_date' => $transportTracking->client_date?->format('d/m/Y'),
                'commune_date' => $transportTracking->commune_date,
                'provider_gross_weight' => $transportTracking->provider_gross_weight,
                'provider_tare_weight' => $transportTracking->provider_tare_weight,
                'provider_net_weight' => $transportTracking->provider_net_weight,
                'client_gross_weight' => $transportTracking->client_gross_weight,
                'client_tare_weight' => $transportTracking->client_tare_weight,
                'client_net_weight' => $transportTracking->client_net_weight,
                'commune_weight' => $transportTracking->commune_weight,
                'gap' => $transportTracking->gap,
                'documents' => $transportTracking->documents->map(fn ($d) => [
                    'id' => $d->id,
                    'original_name' => $d->original_name,
                    'mime_type' => $d->mime_type,
                    'type' => $d->type,
                    'file_url' => asset('storage/' . $d->file_path),
                ]),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TransportTracking $transportTracking)
    {
        return $this->editPage($transportTracking);
    }

    public function editPage(TransportTracking $transportTracking)
    {
        $transportTracking->load('documents');

        // Include soft-deleted records that are currently assigned to this tracking
        $transporters = Transporter::when($transportTracking->truck?->transporter_id, function ($q, $id) {
            $q->orWhere(fn ($q2) => $q2->withTrashed()->where('id', $id));
        })->get();
        $trucks = Truck::when($transportTracking->truck_id, function ($q, $id) {
                $q->orWhere(fn ($q2) => $q2->withTrashed()->where('id', $id));
            })->get()
            ->map(function ($truck) {
                return [
                    'id' => $truck->id,
                    'matricule' => $truck->matricule,
                    'driver_id' => $truck->transportTrackings->first()?->driver_id,
                    'transporter_id' => $truck?->transporter_id,
                ];
            });
        $drivers = Driver::when($transportTracking->driver_id, function ($q, $id) {
            $q->orWhere(fn ($q2) => $q2->withTrashed()->where('id', $id));
        })->get();
        $providers = Provider::when($transportTracking->provider_id, function ($q, $id) {
            $q->orWhere(fn ($q2) => $q2->withTrashed()->where('id', $id));
        })->get();
        $products = collect(['0/3', '3/8', '8/16'])->map(function ($product) {
            return [
                'id' => $product,
                'name' => $product
            ];
        });
        $bases = collect([
            ['id' => 'mr', 'name' => 'Mauritanie'],
            ['id' => 'sn', 'name' => 'Sénégal'],
            ['id' => 'none', 'name' => 'Non definie'],
        ]);

        return Inertia::render('transport-trackings/Edit', [
            'transportTracking' => [
                'id' => $transportTracking->id,
                'reference' => $transportTracking->reference,
                'truck_id' => $transportTracking->truck_id,
                'driver_id' => $transportTracking->driver_id,
                'provider_id' => $transportTracking->provider_id,
                'product' => $transportTracking->product,
                'base' => $transportTracking->base,
                'provider_date' => $transportTracking->provider_date,
                'client_date' => $transportTracking->client_date,
                'commune_date' => $transportTracking->commune_date,
                'commune_weight' => $transportTracking->commune_weight,
                'provider_gross_weight' => $transportTracking->provider_gross_weight,
                'provider_tare_weight' => $transportTracking->provider_tare_weight,
                'provider_net_weight' => $transportTracking->provider_net_weight,
                'client_gross_weight' => $transportTracking->client_gross_weight,
                'client_tare_weight' => $transportTracking->client_tare_weight,
                'client_net_weight' => $transportTracking->client_net_weight,
                'documents' => $transportTracking->documents->map(fn ($d) => [
                    'id' => $d->id,
                    'original_name' => $d->original_name,
                    'mime_type' => $d->mime_type,
                    'type' => $d->type,
                    'file_url' => Storage::url($d->file_path),
                ])->toArray(),
            ],
            'transporters' => $transporters->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->toArray(),
            'trucks' => $trucks->toArray(),
            'drivers' => $drivers->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->toArray(),
            'providers' => $providers->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->toArray(),
            'products' => $products->toArray(),
            'bases' => $bases->toArray(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TransportTracking $transportTracking)
    {
        $validated = $request->validate([
            'truck_id' => 'required|string',
            'driver_id' => 'required|string',
            'provider_id' => 'nullable|exists:providers,id',

            'product' => 'required|in:0/3,3/8,8/16',
            'base' => 'required|in:mr,sn,none',

            'provider_date' => 'nullable|date',
            'client_date' => 'nullable|date',
            'commune_date' => 'nullable|date',
            'commune_weight' => 'nullable|numeric',

            'files.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:4096',

            'provider_gross_weight' => 'nullable|numeric',
            'provider_tare_weight' => 'nullable|numeric',
            'provider_net_weight' => 'nullable|numeric',

            'client_gross_weight' => 'nullable|numeric',
            'client_tare_weight' => 'nullable|numeric',
            'client_net_weight' => 'nullable|numeric',
        ]);

        /** ---------------- Resolve Truck ---------------- */
        $truckInput = $validated['truck_id'];

        $truck = is_numeric($truckInput)
            ? Truck::find($truckInput)
            : Truck::where('matricule', $truckInput)->first();

        if (!$truck) {
            $truck = Truck::create([
                'matricule' => $truckInput,
                'transporter_id' => $request->input('transporter_id'),
            ]);
        }

        /** ---------------- Resolve Driver ---------------- */
        $driverInput = $validated['driver_id'];

        $driver = is_numeric($driverInput)
            ? Driver::find($driverInput)
            : Driver::where('name', $driverInput)->first();

        if (!$driver) {
            $driver = Driver::create(['name' => $driverInput]);
        }

        /** ---------------- Prepare Data ---------------- */
        $data = [
            'truck_id' => $truck->id,
            'driver_id' => $driver->id,
            'provider_id' => $validated['provider_id'] ?? null,
            'product' => $validated['product'],
            'base' => $validated['base'],
            'provider_date' => $validated['provider_date'] ?? null,
            'client_date' => $validated['client_date'] ?? null,
            'commune_date' => $validated['commune_date'] ?? null,
            'commune_weight' => $validated['commune_weight'] ?? null,
            'provider_gross_weight' => $validated['provider_gross_weight'] ?? null,
            'provider_tare_weight' => $validated['provider_tare_weight'] ?? null,
            'provider_net_weight' => $validated['provider_net_weight'] ?? null,
            'client_gross_weight' => $validated['client_gross_weight'] ?? null,
            'client_tare_weight' => $validated['client_tare_weight'] ?? null,
            'client_net_weight' => $validated['client_net_weight'] ?? null,
        ];

        /** ---------------- Update Record ---------------- */
        $transportTracking->update($data);

        /** ---------------- Files (Append mode) ---------------- */
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('transport_trackings', 'public');

                // Determine file type from filename or default to 'other'
                $originalName = strtolower($file->getClientOriginalName());
                $type = 'other';
                if (strpos($originalName, 'provider') !== false || strpos($originalName, 'fournisseur') !== false) {
                    $type = 'provider';
                } elseif (strpos($originalName, 'client') !== false) {
                    $type = 'client';
                } elseif (strpos($originalName, 'commune') !== false) {
                    $type = 'commune';
                }

                Document::create([
                    'transport_tracking_id' => $transportTracking->id,
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'type' => $type,
                ]);
            }
        }

        return redirect()
            ->route('transport_tracking.index')
            ->with('success', 'Stock updated successfully');
    }

    /**
     * Delete a document
     */
    public function deleteDocument($id, $documentId): JsonResponse
    {
        $tracking = TransportTracking::findOrFail($id);
        $document = Document::where('id', $documentId)
            ->where('transport_tracking_id', $tracking->id)
            ->firstOrFail();

        // Delete file from storage
        if (Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        // Delete document record
        $document->delete();

        return response()->json([
            'success' => true,
            'message' => 'Document deleted successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TransportTracking $transportTracking)
    {
        // Soft-delete all associated documents (files kept on disk for restore)
        $transportTracking->documents()->delete();

        $transportTracking->delete();
        return response()->json([
            'success' => true,
            'message' => 'Stock deleted successfully',
        ]);
    }

    // askAI
    public function askAI()
    {
        return view('pages.transport_trackings.ask-ai');
    }

    //analyze
    public function analyze(Request $request)
    {
        $question = $request->input('question');
//        dd($question);

        $prompt = <<<EOT
You are a non SQL expert but you have data stored in a SQL database.
You receive questions and return human analyzed data that works on a table called `transport_trackings` with these fields:
- id (int)
- truck_id (int)
- driver_id (int)
- provider_id (int)
- product (string)
- provider_date (datetime)
- client_date (datetime)
- provider_file (string)
- client_file (string)
- provider_reference (string)
- client_reference (string)
- provider_gross_weight (float)
- provider_net_weight (float)
- provider_tare_weight (float)
- client_gross_weight (float)
- client_net_weight (float)
- client_tare_weight (float)
- created_at (datetime)
- updated_at (datetime)
and relations:
- truck (id, matricule)
- truck.transporter (id, name)
- driver (id, name)
- provider (id, name)
Make sure to use the correct table and column names.
Return only the analyzed data in a JSON format that can be displayed as ul list or in a table.
Do not return SQL queries.
"$question"
EOT;


        $response = OpenAI::chat()->create([
            'model' => 'gpt-4',
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        $aiResponse = $response->choices[0]->message->content;


        return response()->json([
            'data' => $aiResponse,
            'question' => $question,
            'message' => 'Data analyzed successfully',
            'success' => true,
        ]);
    }

    public function analyzeAll(Request $request)
    {
        $question = $request->input('question');

        $data = TransportTracking::with(['driver', 'truck.transporter', 'provider'])
            ->get()
            ->map(function ($item) {
                return [
                    'provider_date' => $item->provider_date,
                    'client_date' => $item->client_date,
                    'product' => $item->product,
                    'provider_net_weight' => $item->provider_net_weight,
                    'client_net_weight' => $item->client_net_weight,
                    'driver' => $item->driver->name ?? '',
                    'truck' => $item->truck->matricule ?? '',
                    'transporter' => $item->truck?->transporter->name ?? '',
                    'provider' => $item->provider->name ?? '',
                ];
            });

        $prompt = <<<EOT
You are an expert data analyst specialized in transport and logistics.

I will provide you with an array of transport records containing:
- provider_date
- client_date
- product
- provider_net_weight
- client_net_weight
- driver
- truck
- transporter
- provider

Your task:
1. Compare provider_net_weight vs client_net_weight (detect losses or gains).
2. Identify if discrepancies are linked to a specific provider, transporter, driver, or truck.
3. Provide:
   - Summary stats (total lost/gained, average difference).
   - Suspicious providers, drivers, trucks, or transporters.
   - Unusual cases (client > provider, large discrepancies).
4. End with a clear **conclusion on who is most likely responsible**.

User question: "{$question}"

⚠️ Output the results as **HTML only** (tables, lists, paragraphs). Do not explain that you are outputting HTML.
EOT;

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4', // or 'gpt-3.5-turbo' if that's your access level
            'messages' => [
                ['role' => 'system', 'content' => 'You are a professional logistics data analyst.'],
                ['role' => 'user', 'content' => $prompt . "\n\nData:\n" . json_encode($data, JSON_PRETTY_PRINT)],
            ],
        ]);

        $aiResponse = $response->choices[0]->message->content;

        return response()->json([
            'html' => $aiResponse, // ready to render in Blade
            'question' => $question,
            'message' => 'Data analyzed successfully',
            'success' => true,
        ]);
    }


    // dashboard

    public function dashboard()
    {
        $filters = [
            'transporter_id' => request('transporter_id'),
            'truck_id' => request('truck_id'),
            'driver_id' => request('driver_id'),
            'provider_id' => request('provider_id'),
            'start_date' => request('start_date'),
            'end_date' => request('end_date'),
        ];

        $q = TransportTracking::query();

        foreach (['truck_id', 'driver_id', 'provider_id'] as $f) {
            if (!empty($filters[$f])) $q->where($f, $filters[$f]);
        }
        if (!empty($filters['transporter_id'])) {
            $q->whereHas('truck', fn ($qr) => $qr->where('transporter_id', $filters['transporter_id']));
        }
        if (!empty($filters['start_date'])) $q->whereDate('provider_date', '>=', $filters['start_date']);
        if (!empty($filters['end_date'])) $q->whereDate('client_date', '<=', $filters['end_date']);

        $threshold = config('logistics.weight_anomaly_threshold', 0.2);
        $totalTransported = (clone $q)->sum('provider_net_weight');
        $totalReceived = (clone $q)->sum('client_net_weight');
        $totalCount = (clone $q)->count();

        $totalDifference = (clone $q)
            ->whereRaw('provider_net_weight > client_net_weight AND provider_net_weight - client_net_weight > ?', [$threshold])
            ->sum(DB::raw('provider_net_weight - client_net_weight'));

        $totalRotationsPerdues = (clone $q)->whereRaw('(provider_net_weight - client_net_weight) > ?', [$threshold])->count();
        $totalRotationsNormal = (clone $q)->whereRaw('ABS(client_net_weight - provider_net_weight) BETWEEN 0 AND ?', [$threshold])->count();
        $totalPoidsAnomalies = (clone $q)->whereRaw('client_net_weight > provider_net_weight')->sum(DB::raw('client_net_weight - provider_net_weight'));

        // Monthly data
        $year = date('Y');
        $monthLabels = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'];
        $monthlyWeights = collect(range(1, 12))->map(fn ($m) => (clone $q)->whereYear('provider_date', $year)->whereMonth('provider_date', $m)->sum('provider_net_weight'))->toArray();

        // Timeline events for Gantt
        $timelineEvents = (clone $q)->with(['truck', 'driver'])->latest('provider_date')->take(100)->get()->map(fn ($t) => [
            'truck' => $t->truck?->matricule ?? 'N/A',
            'driver' => $t->driver?->name ?? 'N/A',
            'start' => $t->provider_date?->format('Y-m-d'),
            'end' => $t->client_date?->format('Y-m-d') ?? $t->provider_date?->format('Y-m-d'),
            'reference' => $t->reference,
            'hasConflict' => false,
        ]);

        // Detect conflicts (same truck, overlapping dates)
        $byTruck = $timelineEvents->groupBy('truck');
        $timelineWithConflicts = $timelineEvents->map(function ($event) use ($byTruck) {
            $siblings = $byTruck[$event['truck']] ?? collect();
            foreach ($siblings as $other) {
                if ($other['reference'] === $event['reference']) continue;
                if ($event['start'] <= $other['end'] && $event['end'] >= $other['start']) {
                    $event['hasConflict'] = true;
                    break;
                }
            }
            return $event;
        });

        $safe = fn ($v, $total) => $total > 0 ? round(($v / $total) * 100, 1) : 0;

        return \Inertia\Inertia::render('TransportDashboard', [
            'filters' => $filters,
            'filterOptions' => [
                'transporters' => Transporter::whereHas('trucks.transportTrackings')->get()->map(fn ($t) => ['value' => $t->id, 'label' => $t->name]),
                'trucks' => Truck::whereHas('transportTrackings')->get()->map(fn ($t) => ['value' => $t->id, 'label' => $t->matricule]),
                'drivers' => Driver::whereHas('transportTrackings')->get()->map(fn ($d) => ['value' => $d->id, 'label' => $d->name]),
                'providers' => Provider::whereHas('transportTrackings')->get()->map(fn ($p) => ['value' => $p->id, 'label' => $p->name]),
            ],
            'kpis' => [
                'totalTransported' => round($totalTransported, 2),
                'totalReceived' => round($totalReceived, 2),
                'totalDifference' => round($totalDifference, 2),
                'totalPoidsAnomalies' => round($totalPoidsAnomalies, 2),
                'totalCount' => $totalCount,
                'rotationsPerdues' => $totalRotationsPerdues,
                'rotationsNormal' => $totalRotationsNormal,
                'pctReceived' => $safe($totalReceived, $totalTransported),
                'pctDifference' => $safe($totalDifference, $totalTransported),
                'pctAnomalies' => $safe($totalPoidsAnomalies, $totalTransported),
                'pctPerdues' => $safe($totalRotationsPerdues, $totalCount),
                'pctNormal' => $safe($totalRotationsNormal, $totalCount),
            ],
            'months' => $monthLabels,
            'monthlyWeights' => $monthlyWeights,
            'timelineEvents' => $timelineWithConflicts->values(),
        ]);
    }

    // dashboard
    /* public function dashboard()
      {
          // Fetch all data
          $trackings = TransportTracking::all()->map(function($item){
              // Parse numeric values
              $item->client_net_weight = (float) str_replace(',', '.', $item->client_net_weight);
              $item->client_gross_weight = (float) str_replace(',', '.', $item->client_gross_weight);
              $item->client_tare_weight = (float) str_replace(',', '.', $item->client_tare_weight);
              $item->provider_net_weight = (float) str_replace(',', '.', $item->provider_net_weight);
              $item->provider_gross_weight = (float) str_replace(',', '.', $item->provider_gross_weight);
              $item->provider_tare_weight = (float) str_replace(',', '.', $item->provider_tare_weight);
              $item->gap = (float) str_replace(',', '.', strip_tags($item->gap));

              // Differences
              $item->gross_net_diff_client = $item->client_gross_weight - $item->client_net_weight;
              $item->gross_net_diff_provider = $item->provider_gross_weight - $item->provider_net_weight;
              $item->client_provider_diff = $item->client_net_weight - $item->provider_net_weight;

              // Ensure base and product_type exist
              $item->base = $item->base ?? 'sn';
              $item->product = $item->product ?? 'unknown';

              return $item;
          });

          // Driver stats
          $driverStats = $trackings->groupBy('driver_id')->map(function($group){
              return [
                  'total_deliveries' => $group->count(),
                  'total_gap' => $group->sum('gap'),
                  'avg_gap' => round($group->avg('gap'), 2),
                  'by_base' => $group->groupBy('base')->map(fn($g,$base)=>['total_deliveries'=>$g->count(),'total_gap'=>$g->sum('gap')]),
                  'by_product_type' => $group->groupBy('product')->map(fn($g,$type)=>['total_deliveries'=>$g->count(),'total_gap'=>$g->sum('gap')]),
              ];
          });

          // Transporter stats
          $transporterStats = $trackings->groupBy('transporter_id')->map(function($group){
              return [
                  'total_deliveries' => $group->count(),
                  'total_gap' => $group->sum('gap'),
                  'by_base' => $group->groupBy('base')->map(fn($g,$base)=>['total_deliveries'=>$g->count(),'total_gap'=>$g->sum('gap')]),
                  'by_product_type' => $group->groupBy('product')->map(fn($g,$type)=>['total_deliveries'=>$g->count(),'total_gap'=>$g->sum('gap')]),
              ];
          });

          // Daily stats
          $dailyStats = $trackings->groupBy('date')->map(function($group){
              return [
                  'total_deliveries' => $group->count(),
                  'total_client_weight' => $group->sum('client_net_weight'),
                  'total_provider_weight' => $group->sum('provider_net_weight'),
                  'avg_gap' => round($group->avg('gap'), 2),
                  'by_base' => $group->groupBy('base')->map(fn($g,$base)=>['total_deliveries'=>$g->count(),'total_gap'=>$g->sum('gap')]),
              ];
          });

          return view('pages.transport_trackings.dashboard', compact('driverStats','transporterStats','dailyStats','trackings'));
      }*/


    public function export(Request $request)
    {

        $filters = [
            'start_date' => request('start_date'),
            'end_date' => request('end_date'),
            'driver_id_filter' => request('driver_id_filter'),
            'provider_id_filter' => request('provider_id_filter'),
            'truck_id_filter' => request('truck_id_filter'),
            'transporter_id_filter' => request('transporter_id_filter'),
        ];

//        dd($filters);

        return Excel::download(new TransportTrackingExport($filters), 'transport_tracking.xlsx');
    }

    public function exportMissing()
    {

        return Excel::download(new MissingTransportTrackingExport, 'missing_transport_trackings.xlsx');

    }

}

