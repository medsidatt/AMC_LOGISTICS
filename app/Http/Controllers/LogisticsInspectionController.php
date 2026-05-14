<?php

namespace App\Http\Controllers;

use App\Models\Auth\User;
use App\Models\InspectionChecklist;
use App\Models\InspectionChecklistIssue;
use App\Models\Truck;
use App\Notifications\InspectionSubmittedNotification;
use App\Services\SharePointStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

class LogisticsInspectionController extends Controller
{
    public function __construct(
        private readonly SharePointStorageService $sharePointStorage,
    ) {
        $this->middleware('permission:inspection-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:inspection-edit', ['only' => ['edit', 'update']]);
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
        $data = $this->validateInspection($request, true);

        try {
            DB::beginTransaction();

            $inspection = InspectionChecklist::create(array_merge(
                $this->payload($data),
                [
                    'inspector_id' => auth()->id(),
                    'status' => InspectionChecklist::STATUS_SUBMITTED,
                ]
            ));

            $this->syncIssues($inspection, $data);
            $this->handleAttachmentUpload($request, $inspection);

            DB::commit();

            $this->notifyHse($inspection);

            return redirect()
                ->route('hse.inspections.show', $inspection->id)
                ->with('success', 'Inspection enregistrée avec succès.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Inspection creation failed', ['error' => $e->getMessage()]);
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function edit(InspectionChecklist $inspection)
    {
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
        $data = $this->validateInspection($request, false);

        $wasDraft = $inspection->status === InspectionChecklist::STATUS_DRAFT;

        $inspection->fill($this->payload($data, false));
        $inspection->status = InspectionChecklist::STATUS_SUBMITTED;
        $inspection->save();

        $this->handleAttachmentUpload($request, $inspection);

        if ($wasDraft) {
            $this->notifyHse($inspection);
        }

        return redirect()
            ->route('hse.inspections.show', $inspection->id)
            ->with('success', 'Inspection mise à jour.');
    }

    private function validateInspection(Request $request, bool $isCreate): array
    {
        $conditionKeys = implode(',', array_keys(InspectionChecklist::CONDITION_OPTIONS));
        $categoryKeys = implode(',', array_keys(InspectionChecklist::CATEGORY_OPTIONS));

        $rules = [
            'inspection_date' => 'required|date',
            'category' => "required|string|in:{$categoryKeys}",
            'findings_summary' => 'nullable|string|max:2000',
            'recommendations' => 'nullable|string|max:2000',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ];

        if ($isCreate) {
            $rules['truck_id'] = 'required|exists:trucks,id';
            $rules['issue_flags'] = 'nullable|array';
            $rules['issue_flags.*'] = 'string|max:50';
            $rules['issue_notes'] = 'nullable|array';
            $rules['issue_notes.*'] = 'nullable|string|max:500';
            $rules['issue_severity'] = 'nullable|array';
            $rules['issue_severity.*'] = 'nullable|string|in:minor,major,critical';
        }

        foreach (InspectionChecklist::INSPECTION_FIELDS as $field) {
            $rules[$field] = "nullable|string|in:{$conditionKeys}";
        }

        return $request->validate($rules);
    }

    private function payload(array $data, bool $isCreate = true): array
    {
        $base = ['inspection_date', 'category', 'findings_summary', 'recommendations'];
        if ($isCreate) {
            $base[] = 'truck_id';
        }
        return array_intersect_key(
            $data,
            array_flip(array_merge($base, InspectionChecklist::INSPECTION_FIELDS))
        );
    }

    private function syncIssues(InspectionChecklist $inspection, array $data): void
    {
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
    }

    private function handleAttachmentUpload(Request $request, InspectionChecklist $inspection): void
    {
        if (!$request->hasFile('attachment')) {
            return;
        }

        if (!$this->sharePointStorage->isConfigured()) {
            Log::warning('SharePoint not configured — skipping inspection attachment upload', [
                'inspection_id' => $inspection->id,
            ]);
            return;
        }

        $file = $request->file('attachment');
        $result = $this->sharePointStorage->upload($file, 'inspections');

        if (!($result['success'] ?? false)) {
            Log::warning('Inspection attachment upload failed', [
                'inspection_id' => $inspection->id,
                'message' => $result['message'] ?? null,
            ]);
            return;
        }

        $inspection->update([
            'attachment_path' => $result['path'],
            'attachment_url' => $result['url'],
            'attachment_filename' => $file->getClientOriginalName(),
        ]);
    }

    private function notifyHse(InspectionChecklist $inspection): void
    {
        try {
            $hseAgents = User::role('HSE Agent')->get();
            if ($hseAgents->isNotEmpty()) {
                Notification::send($hseAgents, new InspectionSubmittedNotification($inspection));
            }
        } catch (\Throwable $e) {
            Log::warning('HSE notification dispatch failed', [
                'inspection_id' => $inspection->id,
                'error' => $e->getMessage(),
            ]);
        }
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
            ])->values()->toArray(),
        ];
        foreach (InspectionChecklist::INSPECTION_FIELDS as $field) {
            $base[$field] = $inspection->{$field};
        }
        return $base;
    }
}
