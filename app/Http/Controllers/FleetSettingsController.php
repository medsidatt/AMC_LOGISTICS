<?php

namespace App\Http\Controllers;

use App\Models\FleetSetting;
use App\Models\MonthlyTonnageTarget;
use App\Services\ObjectiveHistoryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FleetSettingsController extends Controller
{
    public function __construct(private readonly ObjectiveHistoryService $objectiveHistory)
    {
        $this->middleware('auth');
        $this->middleware('permission:fleet-settings-edit');
    }

    public function edit()
    {
        $setting = FleetSetting::current();

        $start = now()->subMonths(12)->startOfMonth();
        $end = now()->addMonths(11)->startOfMonth();
        $months = [];
        $cursor = $start->copy();
        while ($cursor->lessThanOrEqualTo($end)) {
            $months[] = [
                'year' => $cursor->year,
                'month' => $cursor->month,
                'label' => $cursor->translatedFormat('F Y'),
            ];
            $cursor->addMonth();
        }

        $stored = MonthlyTonnageTarget::query()
            ->whereIn('year', array_unique(array_column($months, 'year')))
            ->get()
            ->keyBy(fn ($r) => $r->year . '-' . $r->month);

        $defaultTarget = MonthlyTonnageTarget::defaultTarget();
        $targets = array_map(function ($m) use ($stored, $defaultTarget) {
            $key = $m['year'] . '-' . $m['month'];
            $row = $stored->get($key);
            return [
                'year' => $m['year'],
                'month' => $m['month'],
                'label' => $m['label'],
                'target' => $row ? (float) $row->target_tonnage : null,
                'effective' => $row ? (float) $row->target_tonnage : $defaultTarget,
                'is_default' => ! $row,
            ];
        }, $months);

        return Inertia::render('settings/FleetSettings', [
            'setting' => [
                'monthly_target_tonnage' => (float) $setting->monthly_target_tonnage,
                'weight_gap_threshold' => (float) $setting->weight_gap_threshold,
                'price_per_litre' => (float) $setting->price_per_litre,
                'target_rotations_per_week' => (int) ($setting->target_rotations_per_week ?? 3),
                'default_capacity_tonnage' => (float) ($setting->default_capacity_tonnage ?? 45),
            ],
            'defaultTarget' => $defaultTarget,
            'monthlyTargets' => $targets,
        ]);
    }

    public function update(Request $request)
    {
        $setting = FleetSetting::current();

        $tracked = [
            'target_rotations_per_week' => 'Rotations/semaine cible',
            'default_capacity_tonnage' => 'Capacité par défaut (t)',
            'monthly_target_tonnage' => 'Objectif tonnage mensuel (t)',
        ];

        $changed = false;
        foreach ($tracked as $field => $_) {
            if ((string) $request->input($field) !== (string) $setting->{$field}) {
                $changed = true;
                break;
            }
        }

        $rules = [
            'monthly_target_tonnage' => 'required|numeric|min:0',
            'weight_gap_threshold' => 'required|numeric|min:0',
            'price_per_litre' => 'required|numeric|min:1',
            'target_rotations_per_week' => 'required|integer|min:1|max:14',
            'default_capacity_tonnage' => 'required|numeric|min:1|max:200',
        ];

        if ($changed) {
            $rules['change_note'] = 'required|string|min:5|max:1000';
        }

        $data = $request->validate($rules);
        $note = $data['change_note'] ?? null;
        unset($data['change_note']);

        $oldValues = $setting->only(array_keys($tracked));

        $setting->update($data);

        if ($note) {
            foreach ($tracked as $field => $label) {
                $this->objectiveHistory->record(
                    subject: $setting,
                    subjectLabel: 'Paramètres flotte (global)',
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

    public function updateMonthlyTarget(Request $request)
    {
        $data = $request->validate([
            'year' => 'required|integer|min:2020|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'target_tonnage' => 'nullable|numeric|min:0',
        ]);

        if ($data['target_tonnage'] === null || $data['target_tonnage'] === '') {
            MonthlyTonnageTarget::where('year', $data['year'])
                ->where('month', $data['month'])
                ->delete();
        } else {
            MonthlyTonnageTarget::updateOrCreate(
                ['year' => $data['year'], 'month' => $data['month']],
                ['target_tonnage' => $data['target_tonnage']],
            );
        }

        return back()->with('success', 'Cible mensuelle mise à jour.');
    }
}
