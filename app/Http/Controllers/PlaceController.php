<?php

namespace App\Http\Controllers;

use App\Models\Place;
use App\Models\Provider;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PlaceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:logistics-dashboard');
    }

    public function index()
    {
        $places = Place::query()
            ->with('provider:id,name')
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(fn (Place $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->name,
                'type' => $p->type,
                'latitude' => (float) $p->latitude,
                'longitude' => (float) $p->longitude,
                'radius_m' => (int) $p->radius_m,
                'is_auto_detected' => $p->is_auto_detected,
                'is_active' => $p->is_active,
                'provider' => $p->provider?->only(['id', 'name']),
                'notes' => $p->notes,
            ])
            ->toArray();

        return Inertia::render('logistics/places/Index', [
            'places' => $places,
        ]);
    }

    public function create()
    {
        return Inertia::render('logistics/places/Create', [
            'providers' => Provider::query()->orderBy('name')->get(['id', 'name'])->toArray(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        Place::create(array_merge($data, [
            'is_auto_detected' => false,
        ]));

        return redirect()->route('places.index')->with('success', 'Lieu créé.');
    }

    public function edit(Place $place)
    {
        return Inertia::render('logistics/places/Edit', [
            'place' => [
                'id' => $place->id,
                'code' => $place->code,
                'name' => $place->name,
                'type' => $place->type,
                'latitude' => (float) $place->latitude,
                'longitude' => (float) $place->longitude,
                'radius_m' => (int) $place->radius_m,
                'provider_id' => $place->provider_id,
                'is_active' => $place->is_active,
                'is_auto_detected' => $place->is_auto_detected,
                'notes' => $place->notes,
            ],
            'providers' => Provider::query()->orderBy('name')->get(['id', 'name'])->toArray(),
        ]);
    }

    public function update(Request $request, Place $place)
    {
        $data = $this->validatedData($request);

        // Auto-detected base centroids are refreshed nightly; we still allow
        // humans to tweak name/radius/active, but not blindly overwrite
        // coordinates (those get rewritten by the nightly command anyway).
        if ($place->is_auto_detected) {
            unset($data['latitude'], $data['longitude']);
        }

        $place->update($data);

        return redirect()->route('places.index')->with('success', 'Lieu mis à jour.');
    }

    public function destroy(Place $place)
    {
        $place->delete();

        return redirect()->route('places.index')->with('success', 'Lieu supprimé.');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'code' => 'nullable|string|max:40',
            'name' => 'required|string|max:120',
            'type' => 'required|in:base,provider_site,client_site,fuel_station,parking,unknown',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius_m' => 'required|integer|min:50|max:5000',
            'provider_id' => 'nullable|exists:providers,id',
            'is_active' => 'required|boolean',
            'notes' => 'nullable|string|max:2000',
        ]);
    }
}
