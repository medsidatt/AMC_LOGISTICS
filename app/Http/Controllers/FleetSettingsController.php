<?php

namespace App\Http\Controllers;

use App\Models\FleetSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FleetSettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            $user = auth()->user();
            abort_unless($user && $user->hasAnyRole(['Admin', 'Super Admin']), 403);
            return $next($request);
        });
    }

    public function edit()
    {
        $setting = FleetSetting::current();

        return Inertia::render('settings/FleetSettings', [
            'setting' => [
                'monthly_target_tonnage' => (float) $setting->monthly_target_tonnage,
                'weight_gap_threshold' => (float) $setting->weight_gap_threshold,
            ],
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'monthly_target_tonnage' => 'required|numeric|min:0',
            'weight_gap_threshold' => 'required|numeric|min:0',
        ]);

        $setting = FleetSetting::current();
        $setting->update($data);

        return redirect()->route('settings.fleet.edit')->with('success', 'Paramètres mis à jour.');
    }
}
