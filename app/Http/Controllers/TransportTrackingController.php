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

//        dd($filters);

//        dd($request->input('start_date'), $request->input('end_date'));

        if ($request->ajax()) {
            return datatables()
                ->of($transportTrackings)
                ->editColumn('reference', function ($t) {

                    $hasFile = $t->documents()->whereIn('mime_type', ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'])->exists();

                    // Reference link
                    $referenceLink = '
        <a href="' . route('transport_tracking.show-page', $t->id) . '"
           class="text-primary font-weight-bold">
            ' . e($t->reference) . '
        </a>';

                    // File icon — opens combined PDF inline
                    $fileIcon = '';
                    if ($hasFile) {
                        $fileIcon = '
            <a href="' . route('transport_tracking.file-page', $t->id) . '"
               target="_blank"
               class="text-success ml-1" title="Voir fichiers">
                <i class="fas fa-file-alt"></i>
            </a>';
                    }

                    // Truck badge
                    $truckBadge = $t->truck
                        ? '<a href="' . route('trucks.show-page', $t->truck->id) . '" class="badge bg-info text-dark mr-1" style="cursor:pointer; font-size:0.75rem;"><i class="fas fa-truck fa-xs"></i> ' . e($t->truck->matricule) . '</a>'
                        : '';

                    // Driver badge
                    $driverBadge = $t->driver
                        ? '<a href="' . route('drivers.show-page', $t->driver->id) . '" class="badge bg-warning text-dark mr-1" style="cursor:pointer; font-size:0.75rem;"><i class="fas fa-user fa-xs"></i> ' . e($t->driver->name) . '</a>'
                        : '';

                    return '<div style="white-space:nowrap;">' . $referenceLink . '</div>'
                         . '<div class="mt-1" style="white-space:nowrap;">' . $truckBadge . $driverBadge . $fileIcon . '</div>';
                })
                ->editColumn('client_date', function ($t) {
                    if (!$t->client_date) return '<span class="text-muted">—</span>';
                    return \Carbon\Carbon::parse($t->client_date)->format('d/m/Y');
                })
                ->editColumn('provider_net_weight', function ($t) {
                    return $t->provider_net_weight !== null
                        ? number_format($t->provider_net_weight, 2, '.', ' ')
                        : '<span class="text-muted">—</span>';
                })
                ->editColumn('client_net_weight', function ($t) {
                    return $t->client_net_weight !== null
                        ? number_format($t->client_net_weight, 2, '.', ' ')
                        : '<span class="text-muted">—</span>';
                })
                ->editColumn('gap', function ($t) {
                    $gap = $t->gap;
                    if ($gap < 0) {
                        $bgClass = 'danger';
                        $icon = '<i class="fas fa-arrow-down fa-xs"></i>';
                    } elseif ($gap <= 0.5) {
                        $bgClass = 'warning';
                        $icon = '<i class="fas fa-equals fa-xs"></i>';
                    } else {
                        $bgClass = 'success';
                        $icon = '<i class="fas fa-arrow-up fa-xs"></i>';
                    }
                    return '<span class="badge badge-' . $bgClass . '" style="font-size:0.8rem;">'
                        . $icon . ' ' . number_format($gap, 2, '.', ' ') . '</span>';
                })
                ->addColumn('actions', function ($transportTracking) {
                    $actions = [
                        [
                            'label' => 'Voir Détails',
                            'href' => route('transport_tracking.show-page', $transportTracking->id),
                            'permission' => true
                        ],
                        [
                            'label' => 'Modifier',
                            'href' => route('transport_tracking.edit-page', $transportTracking->id),
                            'permission' => true
                        ],
                        [
                            'label' => 'Supprimer',
                            'onclick' => 'confirmDelete("' . route('transport_tracking.destroy', $transportTracking->id) . '")',
                            'permission' => true
                        ]
                    ];
                    return view('components.buttons.action', compact('actions'));
                })
                ->rawColumns(['actions', 'reference', 'gap', 'client_date', 'provider_net_weight', 'client_net_weight'])
                ->make(true);
        }

        $actions = [
            [
                'label' => 'Ajouter stock',
                'url' => route('transport_tracking.create-page'),
                'permission' => true
            ],
            // import button
            [
                'label' => 'Importer',
                'onclick' => 'showModal({
                    title: "Importer Transport Tracking",
                    route: "' . route('transport_tracking.import') . '",
                    size: "md"
                })',
                'permission' => true
            ],
            // export data's button
            [
                'label' => 'Exporter',
                'url' => route('transport_tracking.export'),
                'permission' => true,
                'onclick' => 'exportFiltered(event)'
            ],
            // export missing data's button
            [
                'label' => 'Exporter incomplets',
                'url' => route('transport_tracking.export-missing'),
                'permission' => true
            ],
        ];

        return view('pages.transport_trackings.index', [
            'title' => 'Transport Tracking',
            'actions' => $actions,
            'breadcrumbs' => [
                [
                    'label' => __('Transport Tracking'),
                    'url' => route('transport_tracking.index')
                ],
            ],
            'transportTrackings' => $transportTrackings->get(),
            'transporters' => Transporter::all(),
            'trucks' => Truck::all(),
            'drivers' => Driver::all(),
            'providers' => Provider::all(),
            'products' => collect(['0/3', '3/8', '8/16'])->map(function ($product) {
                return [
                    'id' => $product,
                    'name' => $product
                ];
            })->toArray(),
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

        return view('pages.transport_trackings.create-page', [
            'transporters' => $transporters,
            'trucks' => $trucks,
            'drivers' => $drivers,
            'providers' => $providers,
            'products' => $products,
            'bases' => $bases,
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

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Stock saved successfully',
                'id' => $record->id,
            ]);
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
        $transportTracking->load('documents');

        $title = 'Detail deposit ' . $transportTracking->reference;
        $actions = [
            [
                'label' => 'Modifier',
                'url' => route('transport_tracking.edit-page', $transportTracking->id),
                'permission' => true
            ],
            [
                'label' => 'Supprimer',
                'onclick' => 'confirmDelete("' . route('transport_tracking.destroy', $transportTracking->id) . '")',
                'permission' => true
            ]
        ];

        return view('pages.transport_trackings.show-page', [
            'title' => $title,
            'actions' => $actions,
            'tracking' => $transportTracking,
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

        return view('pages.transport_trackings.edit-page', [
            'transportTracking' => $transportTracking,
            'transporters' => $transporters,
            'trucks' => $trucks,
            'drivers' => $drivers,
            'providers' => $providers,
            'products' => $products,
            'bases' => $bases,
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

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Stock updated successfully',
                'id' => $transportTracking->id,
            ]);
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
        // Delete all associated documents
        foreach ($transportTracking->documents as $document) {
            if (Storage::disk('public')->exists($document->file_path)) {
                Storage::disk('public')->delete($document->file_path);
            }
            $document->delete();
        }

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
        $title = 'Dashboard';

        // Actions (example)
        $actions = [[
            'label' => 'Ajouter stock',
            'url' => route('transport_tracking.create-page'),
            'permission' => true
        ]];

        $breadcrumbs = [
            ['label' => __('Stock'), 'url' => route('transport_tracking.index')],
            ['label' => __('Dashboard'), 'url' => route('transport_tracking.dashboard')],
        ];

        // Filters
        $filters = [
            'transporter_id' => request('transporter_id'),
            'truck_id' => request('truck_id'),
            'driver_id' => request('driver_id'),
            'provider_id' => request('provider_id'),
            'start_date' => request('start_date'),
            'end_date' => request('end_date'),
            'year' => request('year'),
        ];

        $drivers = Driver::whereHas('transportTrackings')->get();

        $transportTrackings = TransportTracking::query();

        // Apply filters
        foreach (['transporter_id', 'truck_id', 'driver_id', 'provider_id'] as $filter) {
            if (!empty($filters[$filter])) {
                if ($filter === 'transporter_id') {

                    $transportTrackings->whereHas('truck', function ($query) use ($filters, $filter) {
                        $query->where('transporter_id', $filters[$filter]);
                    });
                } else {
                    $transportTrackings->where($filter, $filters[$filter]);
                }
            }
        }

        if (!empty($filters['start_date'])) {
            $transportTrackings->whereDate('provider_date', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $transportTrackings->whereDate('client_date', '<=', $filters['end_date']);
        }

        // Year selection for monthly chart
        $year = $filters['year'] ?? date('Y');

        // Months in French
        $months = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

        // Calculate monthly weights for the selected year
        $monthlyWeights = collect(range(1, 12))->map(function ($month) use ($transportTrackings, $year) {
            return (clone $transportTrackings)
                ->whereYear('provider_date', $year)
                ->whereMonth('provider_date', $month)
                ->sum('provider_net_weight');
        })->toArray();

        // Calendar events
        $events = TransportTracking::all()->map(function ($tracking) {
            return [
                'title' => "{$tracking->reference}",
                'start' => $tracking->provider_date,
                'end' => $tracking->client_date,
            ];
        });

        // Stats
        $query = clone $transportTrackings;
        $thresholdMin = 0;
        $thresholdMax = 0.2;

        $totalTransported = $query->sum('provider_net_weight');
        $totalReceived = $query->sum('client_net_weight');
        $totalCount = $query->count();

        $totalDifference = (clone $query)
            ->whereRaw('provider_net_weight > client_net_weight AND provider_net_weight - client_net_weight > ?', [$thresholdMax])
            ->sum(DB::raw('provider_net_weight - client_net_weight'));

        $totalRotationsPerdues = (clone $query)
            ->whereRaw('(provider_net_weight - client_net_weight) > ?', [$thresholdMax])
            ->count();

        $totalRotationsAnomalies = (clone $query)
            ->whereRaw('(client_net_weight - provider_net_weight) < ?', [$thresholdMax])
            ->count();

        $totalRotationsNormal = (clone $query)
            ->whereRaw('(ABS(client_net_weight - provider_net_weight)) BETWEEN ? AND ?', [$thresholdMin, $thresholdMax])
            ->count();
        $totalPoidsAnomalies = (clone $query)
            ->whereRaw('client_net_weight > provider_net_weight')
            ->sum(DB::raw('client_net_weight - provider_net_weight'));

        if (\request()->ajax()) {
            // Prepare KPI HTML
            $kpiHtml = view('pages.transport_trackings.partials.kpis', [
                'totalTransported' => [
                    'amount' => $totalTransported,
                    'unit' => 't',
                ],
                'totalReceived' => [
                    'amount' => $totalReceived,
                    'percentage' => $totalTransported > 0 ? ($totalReceived / $totalTransported) * 100 : 0,
                    'unit' => 't',
                ],
                'totalDifference' => [
                    'amount' => $totalDifference,
                    'percentage' => $totalTransported > 0 ? ($totalDifference / $totalTransported) * 100 : 0,
                    'unit' => 't',
                ],
                'totalPoidsAnomalies' => [
                    'amount' => $totalPoidsAnomalies,
                    'percentage' => $totalTransported > 0 ? ($totalPoidsAnomalies / $totalTransported) * 100 : 0,
                    'unit' => 't',
                ],
                'totalRotationsPerdues' => [
                    'amount' => $totalRotationsPerdues,
                    'percentage' => $totalCount > 0 ? ($totalRotationsPerdues / $totalCount) * 100 : 0,
                ],
                'totalRotationsAnomalies' => [
                    'amount' => $totalRotationsAnomalies,
                    'percentage' => $totalCount > 0 ? ($totalRotationsAnomalies / $totalCount) * 100 : 0,
                ],
                'totalRotationsNormal' => [
                    'amount' => $totalRotationsNormal,
                    'percentage' => $totalCount > 0 ? ($totalRotationsNormal / $totalCount) * 100 : 0,
                ],
            ])->render();

            // Prepare monthly chart
            $monthlyWeights = collect(range(1, 12))->map(function ($month) use ($transportTrackings, $year) {
                return (clone $transportTrackings)
                    ->whereYear('provider_date', $year)
                    ->whereMonth('provider_date', $month)
                    ->sum('provider_net_weight');
            })->toArray();

            return response()->json([
                'kpis' => $kpiHtml,
                'monthlyWeights' => $monthlyWeights,
            ]);
        }


        return view('pages.transport_trackings.dashboard', [
            'title' => $title,
            'actions' => $actions,
            'breadcrumbs' => $breadcrumbs,
            'transporters' => Transporter::whereHas('trucks', function ($truckQuery) {
                $truckQuery->whereHas('transportTrackings');
            })->get(),
            'trucks' => Truck::whereHas('transportTrackings')->get(),
            'drivers' => $drivers,
            'providers' => Provider::whereHas('transportTrackings')->get(),
            'products' => collect(['0/3', '3/8', '8/16'])->map(fn($p) => ['id' => $p, 'name' => $p])->toArray(),
            'transportTrackings' => $transportTrackings->get(),
            'year' => $year,
            'months' => $months,
            'monthlyWeights' => $monthlyWeights,
            'events' => $events,

            'totalTransported' => [
                'amount' => $totalTransported,
                'unit' => 't',
            ],
            'totalReceived' => [
                'amount' => $totalReceived,
                'percentage' => $totalTransported > 0 ? ($totalReceived / $totalTransported) * 100 : 0,
                'unit' => 't',
            ],
            'totalDifference' => [
                'amount' => $totalDifference,
                'percentage' => $totalTransported > 0 ? ($totalDifference / $totalTransported) * 100 : 0,
                'unit' => 't',
            ],
            'totalPoidsAnomalies' => [
                'amount' => $totalPoidsAnomalies,
                'percentage' => $totalTransported > 0 ? ($totalPoidsAnomalies / $totalTransported) * 100 : 0,
                'unit' => 't',
            ],
            'totalRotationsPerdues' => [
                'amount' => $totalRotationsPerdues,
                'percentage' => $totalCount > 0 ? ($totalRotationsPerdues / $totalCount) * 100 : 0,
            ],
            'totalRotationsAnomalies' => [
                'amount' => $totalRotationsAnomalies,
                'percentage' => $totalCount > 0 ? ($totalRotationsAnomalies / $totalCount) * 100 : 0,
            ],
            'totalRotationsNormal' => [
                'amount' => $totalRotationsNormal,
                'percentage' => $totalCount > 0 ? ($totalRotationsNormal / $totalCount) * 100 : 0,
            ],
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

