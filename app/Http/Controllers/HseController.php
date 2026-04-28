<?php

namespace App\Http\Controllers;

use App\Models\InspectionChecklist;
use App\Models\InspectionChecklistIssue;
use App\Models\Truck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class HseController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:inspection-list', ['only' => ['index', 'show']]);
        $this->middleware('permission:inspection-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:inspection-edit', ['only' => ['edit', 'update']]);
    }

    public function index(Request $request)
    {
        $inspections = InspectionChecklist::query()
            ->with(['truck:id,matricule', 'inspector:id,name', 'validator:id,name'])
            ->withCount('issues')
            ->orderByDesc('inspection_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->through(fn ($i) => [
                'id' => $i->id,
                'inspection_date' => $i->inspection_date?->format('d/m/Y'),
                'truck' => $i->truck ? ['id' => $i->truck->id, 'matricule' => $i->truck->matricule] : null,
                'inspector' => $i->inspector?->name,
                'category' => $i->category,
                'status' => $i->status,
                'issues_count' => $i->issues_count,
                'validator' => $i->validator?->name,
                'validated_at' => $i->validated_at?->format('d/m/Y H:i'),
            ]);

        return Inertia::render('inspections/Index', [
            'inspections' => $inspections,
            'options' => [
                'categories' => InspectionChecklist::CATEGORY_OPTIONS,
                'conditions' => InspectionChecklist::CONDITION_OPTIONS,
            ],
        ]);
    }

    public function create()
    {
        $trucks = Truck::query()
            ->where('is_active', true)
            ->orderBy('matricule')
            ->get(['id', 'matricule']);

        return Inertia::render('inspections/Create', [
            'trucks' => $trucks,
            'options' => [
                'categories' => InspectionChecklist::CATEGORY_OPTIONS,
                'conditions' => InspectionChecklist::CONDITION_OPTIONS,
                'fields' => InspectionChecklist::INSPECTION_FIELDS,
                'sections' => InspectionChecklist::SECTIONS,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $conditionKeys = implode(',', array_keys(InspectionChecklist::CONDITION_OPTIONS));
        $categoryKeys = implode(',', array_keys(InspectionChecklist::CATEGORY_OPTIONS));

        $rules = [
            'truck_id' => 'required|exists:trucks,id',
            'inspection_date' => 'required|date',
            'category' => "required|string|in:{$categoryKeys}",
            'findings_summary' => 'nullable|string|max:2000',
            'recommendations' => 'nullable|string|max:2000',
            'submit' => 'sometimes|boolean',
            'issue_flags' => 'nullable|array',
            'issue_flags.*' => 'string|max:50',
            'issue_notes' => 'nullable|array',
            'issue_notes.*' => 'nullable|string|max:500',
            'issue_severity' => 'nullable|array',
            'issue_severity.*' => 'nullable|string|in:minor,major,critical',
        ];
        foreach (InspectionChecklist::INSPECTION_FIELDS as $field) {
            $rules[$field] = "nullable|string|in:{$conditionKeys}";
        }

        $data = $request->validate($rules);

        try {
            DB::beginTransaction();

            $inspection = InspectionChecklist::create(array_merge(
                array_intersect_key($data, array_flip(array_merge(
                    ['truck_id', 'inspection_date', 'category', 'findings_summary', 'recommendations'],
                    InspectionChecklist::INSPECTION_FIELDS
                ))),
                [
                    'inspector_id' => auth()->id(),
                    'status' => !empty($data['submit'])
                        ? InspectionChecklist::STATUS_SUBMITTED
                        : InspectionChecklist::STATUS_DRAFT,
                ]
            ));

            $issueFlags = $data['issue_flags'] ?? [];
            $issueNotes = $data['issue_notes'] ?? [];
            $issueSeverity = $data['issue_severity'] ?? [];

            foreach ($issueFlags as $category) {
                InspectionChecklistIssue::create([
                    'inspection_checklist_id' => $inspection->id,
                    'category' => $category,
                    'flagged' => true,
                    'severity' => $issueSeverity[$category] ?? 'minor',
                    'issue_notes' => $issueNotes[$category] ?? null,
                ]);
            }

            DB::commit();

            return redirect()
                ->route('hse.inspections.show', $inspection->id)
                ->with('success', 'Inspection enregistrée avec succès.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(InspectionChecklist $inspection)
    {
        $inspection->load(['truck:id,matricule', 'inspector:id,name', 'validator:id,name', 'issues']);

        return Inertia::render('inspections/Show', [
            'inspection' => $this->serialize($inspection),
            'options' => [
                'categories' => InspectionChecklist::CATEGORY_OPTIONS,
                'conditions' => InspectionChecklist::CONDITION_OPTIONS,
                'fields' => InspectionChecklist::INSPECTION_FIELDS,
                'sections' => InspectionChecklist::SECTIONS,
            ],
        ]);
    }

    public function edit(InspectionChecklist $inspection)
    {
        if (in_array($inspection->status, [InspectionChecklist::STATUS_VALIDATED], true)) {
            return redirect()
                ->route('hse.inspections.show', $inspection->id)
                ->with('error', 'Une inspection validée ne peut pas être modifiée.');
        }

        $inspection->load('issues');
        $trucks = Truck::query()
            ->where('is_active', true)
            ->orderBy('matricule')
            ->get(['id', 'matricule']);

        return Inertia::render('inspections/Edit', [
            'inspection' => $this->serialize($inspection),
            'trucks' => $trucks,
            'options' => [
                'categories' => InspectionChecklist::CATEGORY_OPTIONS,
                'conditions' => InspectionChecklist::CONDITION_OPTIONS,
                'fields' => InspectionChecklist::INSPECTION_FIELDS,
                'sections' => InspectionChecklist::SECTIONS,
            ],
        ]);
    }

    public function update(Request $request, InspectionChecklist $inspection)
    {
        if ($inspection->status === InspectionChecklist::STATUS_VALIDATED) {
            return redirect()->back()->with('error', 'Une inspection validée ne peut pas être modifiée.');
        }

        $conditionKeys = implode(',', array_keys(InspectionChecklist::CONDITION_OPTIONS));
        $categoryKeys = implode(',', array_keys(InspectionChecklist::CATEGORY_OPTIONS));

        $rules = [
            'inspection_date' => 'required|date',
            'category' => "required|string|in:{$categoryKeys}",
            'findings_summary' => 'nullable|string|max:2000',
            'recommendations' => 'nullable|string|max:2000',
            'submit' => 'sometimes|boolean',
        ];
        foreach (InspectionChecklist::INSPECTION_FIELDS as $field) {
            $rules[$field] = "nullable|string|in:{$conditionKeys}";
        }

        $data = $request->validate($rules);

        $inspection->fill(array_intersect_key($data, array_flip(array_merge(
            ['inspection_date', 'category', 'findings_summary', 'recommendations'],
            InspectionChecklist::INSPECTION_FIELDS
        ))));

        if (!empty($data['submit']) && $inspection->status === InspectionChecklist::STATUS_DRAFT) {
            $inspection->status = InspectionChecklist::STATUS_SUBMITTED;
        }

        $inspection->save();

        return redirect()
            ->route('hse.inspections.show', $inspection->id)
            ->with('success', 'Inspection mise à jour.');
    }

    private function serialize(InspectionChecklist $inspection): array
    {
        $base = [
            'id' => $inspection->id,
            'truck' => $inspection->truck ? ['id' => $inspection->truck->id, 'matricule' => $inspection->truck->matricule] : null,
            'inspector' => $inspection->inspector?->name,
            'inspection_date' => $inspection->inspection_date?->format('Y-m-d'),
            'category' => $inspection->category,
            'status' => $inspection->status,
            'findings_summary' => $inspection->findings_summary,
            'recommendations' => $inspection->recommendations,
            'validator' => $inspection->validator?->name,
            'validated_at' => $inspection->validated_at?->format('d/m/Y H:i'),
            'validation_notes' => $inspection->validation_notes,
            'issues' => $inspection->issues->map(fn ($i) => [
                'id' => $i->id,
                'category' => $i->category,
                'severity' => $i->severity,
                'flagged' => $i->flagged,
                'issue_notes' => $i->issue_notes,
                'resolution_notes' => $i->resolution_notes,
                'resolved_at' => $i->resolved_at?->format('d/m/Y H:i'),
            ])->values()->toArray(),
        ];
        foreach (InspectionChecklist::INSPECTION_FIELDS as $field) {
            $base[$field] = $inspection->{$field};
        }
        return $base;
    }
}
