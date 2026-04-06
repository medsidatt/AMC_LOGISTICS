<?php

namespace App\Http\Controllers;

use App\Models\Provider;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProviderController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:provider-list', ['only' => ['index', 'show']]);
        $this->middleware('permission:provider-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:provider-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:provider-delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        $query = Provider::query()->orderBy('name');

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%");
        }

        return Inertia::render('providers/Index', [
            'providers' => $query->paginate(15)->through(fn (Provider $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'phone' => $p->phone,
                'email' => $p->email,
                'address' => $p->address,
                'website' => $p->website,
            ]),
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:providers,name',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|max:255',
        ]);

        Provider::create($request->only('name', 'address', 'phone', 'email', 'website'));

        return redirect()->back()->with('success', 'Fournisseur créé avec succès.');
    }

    public function update(Request $request, Provider $provider)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:providers,name,' . $provider->id,
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|max:255',
        ]);

        $provider->update($request->only('name', 'address', 'phone', 'email', 'website'));

        return redirect()->back()->with('success', 'Fournisseur mis à jour.');
    }

    public function destroy(Provider $provider)
    {
        $provider->delete();

        return redirect()->back()->with('success', 'Fournisseur supprimé.');
    }
}
