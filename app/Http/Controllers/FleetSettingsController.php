<?php

namespace App\Http\Controllers;

use App\Models\FleetSetting;
use App\Models\Truck;
use App\Services\FleetObjectiveService;
use App\Services\ObjectiveHistoryService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Fleet Configuration — global fleet/operational parameters only. Planning
 * objectives (tonnage targets) are NOT owned here; they live solely in the
 * Objectives module (FleetObjective). This page covers Fleet Configuration
 * (capacity, rotation rules) and Operational Parameters (gap threshold, fuel).
 */
class FleetSettingsController extends Controller
{
    public function __construct(
        private readonly ObjectiveHistoryService $objectiveHistory,
        private readonly FleetObjectiveService $fleetObjectives,
    ) {
        $this->middleware('auth');
        $this->middleware('permission:fleet-settings-edit');
    }

    public function edit()
    {
        $setting = FleetSetting::current();

        return Inertia::render('settings/FleetSettings', [
            'setting' => [
                'default_capacity_tonnage' => (float) ($setting->default_capacity_tonnage ?? 45),
                'target_rotations_per_week' => (int) ($setting->target_rotations_per_week ?? 3),
                'weight_gap_threshold' => (float) $setting->weight_gap_threshold,
                'price_per_litre' => (float) $setting->price_per_litre,
            ],
        ]);
    }

    public function update(Request $request)
    {
        $setting = FleetSetting::current();

        // Fleet Configuration changes are audit-tracked (governance).
        $tracked = [
            'default_capacity_tonnage' => 'Capacité par défaut (t)',
            'target_rotations_per_week' => 'Rotations/semaine cible',
        ];

        $changed = false;
        foreach ($tracked as $field => $_) {
            if ((string) $request->input($field) !== (string) $setting->{$field}) {
                $changed = true;
                break;
            }
        }

        $rules = [
            'default_capacity_tonnage' => 'required|numeric|min:1|max:200',
            'target_rotations_per_week' => 'required|integer|min:1|max:14',
            'weight_gap_threshold' => 'required|numeric|min:0',
            'price_per_litre' => 'required|numeric|min:1',
        ];

        if ($changed) {
            $rules['change_note'] = 'required|string|min:5|max:1000';
        }

        $data = $request->validate($rules);
        $note = $data['change_note'] ?? null;
        unset($data['change_note']);

        $oldValues = $setting->only(array_keys($tracked));

        $setting->update($data);

        // Capacity is the single source of truth: when it changes, push it to every
        // truck and re-plan open objectives so it takes effect everywhere at once.
        $capacityChanged = (string) ($oldValues['default_capacity_tonnage'] ?? '') !== (string) ($data['default_capacity_tonnage'] ?? '');
        if ($capacityChanged) {
            Truck::query()->update(['capacity_tonnage' => $data['default_capacity_tonnage']]);
            $this->fleetObjectives->redistributeOpenObjectives();
        }

        if ($note) {
            foreach ($tracked as $field => $label) {
                $this->objectiveHistory->record(
                    subject: $setting,
                    subjectLabel: 'Configuration flotte (global)',
                    fieldName: $field,
                    fieldLabel: $label,
                    oldValue: $oldValues[$field] ?? null,
                    newValue: $data[$field] ?? null,
                    note: $note,
                    context: ['scope' => 'fleet_settings'],
                );
            }
        }

        return redirect()->route('settings.fleet.edit')->with('success', 'Paramètres mis à jour.');
    }
}
