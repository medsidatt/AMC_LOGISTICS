<?php

namespace App\Http\Controllers;

use App\Models\Entity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class EntityController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:entity-list|entity-create|entity-edit|entity-delete', ['only' => ['index']]);
        $this->middleware('permission:entity-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:entity-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:entity-delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        $query = Entity::query()->orderBy('name');

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        }

        return Inertia::render('entities/Index', [
            'entities' => $query->paginate(15)->through(fn (Entity $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'address' => $e->address,
                'phone' => $e->phone,
                'email' => $e->email,
                'website' => $e->website,
                'logo' => $e->logo ? asset('storage/' . $e->logo) : null,
            ]),
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'address' => 'string|nullable',
            'phone' => 'string|nullable',
            'email' => 'string|email|nullable',
            'website' => 'string|url|nullable',
            'logo' => 'image|nullable|max:2048',
        ]);

        Entity::create([
            'name' => $request->name,
            'address' => $request->address,
            'phone' => $request->phone,
            'email' => $request->email,
            'website' => $request->website,
            'logo' => $request->file('logo') ? $request->file('logo')->store('logos', 'public') : null,
        ]);

        return redirect()->back()->with('success', 'Entité créée avec succès.');
    }

    public function update(Request $request, Entity $entity)
    {
        $request->validate([
            'name' => 'required',
            'address' => 'string|nullable',
            'phone' => 'string|nullable',
            'email' => 'string|email|nullable',
            'website' => 'string|nullable',
            'logo' => 'image|nullable|max:2048',
        ]);

        if ($request->file('logo') && $entity->logo) {
            Storage::disk('public')->delete($entity->logo);
        }

        $entity->update([
            'name' => $request->name,
            'address' => $request->address,
            'phone' => $request->phone,
            'email' => $request->email,
            'website' => $request->website,
            'logo' => $request->file('logo') ? $request->file('logo')->store('logos', 'public') : $entity->logo,
        ]);

        return redirect()->back()->with('success', 'Entité mise à jour.');
    }

    public function destroy(Entity $entity)
    {
        $entity->delete();

        return redirect()->back()->with('success', 'Entité supprimée.');
    }
}
