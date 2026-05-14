<?php

namespace App\Http\Controllers;

use App\Models\InspectionChecklist;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HseController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:inspection-list', ['only' => ['index', 'show']]);
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

    public function show(InspectionChecklist $inspection)
    {
        $inspection->load(['truck:id,matricule', 'inspector:id,name', 'validator:id,name', 'issues.maintenance']);

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
            'attachment_url' => $inspection->attachment_url,
            'attachment_filename' => $inspection->attachment_filename,
            'issues' => $inspection->issues->map(fn ($i) => [
                'id' => $i->id,
                'category' => $i->category,
                'severity' => $i->severity,
                'flagged' => $i->flagged,
                'issue_notes' => $i->issue_notes,
                'resolution_notes' => $i->resolution_notes,
                'resolved_at' => $i->resolved_at?->format('d/m/Y H:i'),
                'maintenance_id' => $i->maintenance_id,
                'maintenance_date' => $i->maintenance?->maintenance_date?->format('d/m/Y'),
            ])->values()->toArray(),
        ];
        foreach (InspectionChecklist::INSPECTION_FIELDS as $field) {
            $base[$field] = $inspection->{$field};
        }
        return $base;
    }
}
