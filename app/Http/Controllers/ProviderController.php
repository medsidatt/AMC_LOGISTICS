<?php

namespace App\Http\Controllers;

use App\Models\Provider;
use Illuminate\Http\Request;

class ProviderController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:provider-list', ['only' => ['index', 'show']]);
        $this->middleware('permission:provider-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:provider-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:provider-delete', ['only' => ['destroy']]);
    }

    public function index()
    {
        $providers = Provider::all();
        if (request()->ajax()) {
            return datatables()->of($providers)
                ->addColumn('actions', function ($provider) {

                    $actions = [
                        [
                            'label' => 'Voir Détails',
                            'onclick' => 'showModal({
                                title: \'Détails Fournisseur - '. addslashes($provider->name) .'\',
                                route: \''. route('providers.show', $provider->id) .'\',
                                size: \'md\'
                            })',
                            'permission' => true
                        ],
                        [
                            'label' => 'Modifier',
                            'onclick' => 'showModal({
                                route: \''. route('providers.edit', $provider->id) .'\',
                                title: \'Modifier Fournisseur - '. addslashes($provider->name) .'\',
                                size: \'lg\'
                            })',
                            'permission' => true
                        ],
                        [
                            'label' => 'Supprimer',
                            'onclick' => 'confirmDelete(\''. route('providers.destroy', $provider->id) .'\')',
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
                'label' => 'Add New',
                'onclick' => 'showModal({
                    route: \''. route('providers.create') .'\',
                    title: \'Nouveau Fournisseur\',
                    size: \'lg\'
                })',
                'permission' => true
            ]
        ];

        return view('pages.providers.index', compact('actions'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('pages.providers.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:providers,name',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|max:255',
        ]);

        Provider::firstOrCreate([
            'name' => $request->name,
            'address' => $request->address,
            'phone' => $request->phone,
            'email' => $request->email,
            'website' => $request->website,
        ]);

        return response([
            'success' => 'true',
            'message' => 'Provider created successfully.',
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Provider $provider)
    {
        return view('pages.providers.show', compact('provider'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Provider $provider)
    {
        return view('pages.providers.edit', compact('provider'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Provider $provider)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:providers,name,' . $provider->id,
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
        ]);

        $provider->update([
            'name' => $request->name,
            'address' => $request->address,
            'phone' => $request->phone,
            'email' => $request->email,
            'website' => $request->website,
        ]);

        return response([
            'success' => 'true',
            'message' => 'Provider updated successfully.',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Provider $provider)
    {
        $provider->delete();

        return response([
            'success' => 'true',
            'message' => 'Provider deleted successfully.',
        ]);
    }
}
