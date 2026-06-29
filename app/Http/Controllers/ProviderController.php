<?php

namespace App\Http\Controllers;

use App\Models\Provider;
use App\Support\CounterpartyRules;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProviderController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:provider-list', ['only' => ['index']]);
        $this->middleware('permission:provider-create', ['only' => ['store']]);
        $this->middleware('permission:provider-edit', ['only' => ['update']]);
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
        $request->validate(CounterpartyRules::base('providers'));

        Provider::create($request->only('name', 'address', 'phone', 'email', 'website'));

        return redirect()->back()->with('success', 'Fournisseur créé avec succès.');
    }

    public function update(Request $request, Provider $provider)
    {
        $request->validate(CounterpartyRules::base('providers', $provider->id));

        $provider->update($request->only('name', 'address', 'phone', 'email', 'website'));

        return redirect()->back()->with('success', 'Fournisseur mis à jour.');
    }

    public function destroy(Provider $provider)
    {
        $provider->delete();

        return redirect()->back()->with('success', 'Fournisseur supprimé.');
    }
}
