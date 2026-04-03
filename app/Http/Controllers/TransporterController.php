<?php

namespace App\Http\Controllers;

use App\Models\Transporter;
use Illuminate\Http\Request;
use Yajra\DataTables\Exceptions\Exception;

class TransporterController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:transporter-list', ['only' => ['index', 'show']]);
        $this->middleware('permission:transporter-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:transporter-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:transporter-delete', ['only' => ['destroy']]);
    }

    /**
     * @throws Exception
     * @throws \Exception
     */
    public function index(Request $request)
    {
        $transporters = Transporter::query();

        if ($request->ajax()) {
            return datatables()
                ->of($transporters)
                ->addColumn('actions', function ($transporter) {
                    $actions = [
                        [
                            'label' => 'Voir Détails',
                            'onclick' => 'showModal({
                                title: "Détails Transporteur - ' . addslashes($transporter->name) . '",
                                route: "' . route('transporters.show', $transporter->id) . '",
                                size: "md"
                            })',
                            'permission' => true
                        ],
                        [
                            'label' => 'Modifier',
                            'onclick' => 'showModal({
                                title: "Modifier Transporteur - ' . addslashes($transporter->name) . '",
                                route: "' . route('transporters.edit', $transporter->id) . '",
                                size: "lg"
                            })',
                            'permission' => true
                        ],
                        [
                            'label' => 'Supprimer',
                            'onclick' => 'confirmDelete("' . route('transporters.destroy', $transporter->id) . '")',
                            'permission' => true
                        ]
                    ];
                    return view('components.buttons.action', compact('actions'));
                })
                ->rawColumns(['actions'])
                ->make(true);
        }

        $actions = [
            [
                'label' => 'Nouveau Transporteur',
                'onclick' => 'showModal({
                    title: "Nouveau Transporteur",
                    route: "' . route('transporters.create') . '",
                    size: "lg"
                })',
                'permission' => true
            ]
        ];

        return view('pages.transporters.index', compact('actions'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('pages.transporters.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255'
        ]);

        Transporter::firstOrCreate([
            'name' => $request->name,
            'address' => $request->address,
            'phone' => $request->phone,
            'email' => $request->email,
            'website' => $request->website
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Transporteur créé avec succès'
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Transporter $transporter)
    {
        return view('pages.transporters.show', compact('transporter'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Transporter $transporter)
    {
        return view('pages.transporters.edit', compact('transporter'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Transporter $transporter)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|max:255'
        ]);

        $transporter->update([
            'name' => $request->name,
            'address' => $request->address,
            'phone' => $request->phone,
            'email' => $request->email,
            'website' => $request->website
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Transporteur mis à jour avec succès'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Transporter $transporter)
    {
        $transporter->delete();

        return response()->json([
            'success' => true,
            'message' => 'Transporteur supprimé avec succès'
        ]);
    }
}
