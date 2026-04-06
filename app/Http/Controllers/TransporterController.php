<?php

namespace App\Http\Controllers;

use App\Models\Transporter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TransporterController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:transporter-list', ['only' => ['index', 'show']]);
        $this->middleware('permission:transporter-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:transporter-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:transporter-delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        $query = Transporter::query()->orderBy('name');

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        }

        return Inertia::render('transporters/Index', [
            'transporters' => $query->paginate(15)->through(fn (Transporter $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'phone' => $t->phone,
                'email' => $t->email,
                'address' => $t->address,
                'website' => $t->website,
            ]),
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|max:255',
        ]);

        Transporter::create($request->only('name', 'address', 'phone', 'email', 'website'));

        return redirect()->back()->with('success', 'Transporteur créé avec succès.');
    }

    public function update(Request $request, Transporter $transporter)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|max:255',
        ]);

        $transporter->update($request->only('name', 'address', 'phone', 'email', 'website'));

        return redirect()->back()->with('success', 'Transporteur mis à jour.');
    }

    public function destroy(Transporter $transporter)
    {
        $transporter->delete();

        return redirect()->back()->with('success', 'Transporteur supprimé.');
    }
}
