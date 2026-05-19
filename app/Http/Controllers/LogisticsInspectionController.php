<?php

namespace App\Http\Controllers;

use App\Models\Auth\User;
use App\Models\Driver;
use App\Models\InspectionChecklist;
use App\Models\InspectionChecklistIssue;
use App\Models\Project;
use App\Models\TransportTracking;
use App\Models\Truck;
use App\Notifications\InspectionSubmittedNotification;
use App\Services\SharePointStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
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
        [$truckDrivers, $driverTrucks] = $this->buildTruckDriverMaps();
        $projects = Project::query()->orderBy('name')->get(['id', 'name', 'code']);

        return Inertia::render('inspections/Create', [
            'trucks' => Truck::query()->where('is_active', true)->orderBy('matricule')->get(['id', 'matricule']),
            'drivers' => Driver::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'projects' => $projects,
            'defaultProjectId' => $this->resolveDefaultProjectId($projects),
            'truckDrivers' => $truckDrivers,
            'driverTrucks' => $driverTrucks,
            'options' => [
                'categories' => InspectionChecklist::CATEGORY_OPTIONS,
                'conditions' => InspectionChecklist::CONDITION_OPTIONS,
                'fields' => InspectionChecklist::INSPECTION_FIELDS,
                'sections' => InspectionChecklist::SECTIONS,
            ],
        ]);
    }

    private function resolveDefaultProjectId($projects): ?int
    {
        $match = $projects->first(function ($p) {
            $name = strtolower((string) $p->name);
            return str_contains($name, 'pont') && str_contains($name, 'rosso');
        });
        if ($match) {
            return (int) $match->id;
        }
        return $projects->count() === 1 ? (int) $projects->first()->id : null;
    }

    private function buildTruckDriverMaps(): array
    {
        $pairs = TransportTracking::query()
            ->whereNotNull('driver_id')
            ->select('truck_id', 'driver_id')
            ->distinct()
            ->get();

        $truckDrivers = $pairs
            ->groupBy('truck_id')
            ->map(fn ($rows) => $rows->pluck('driver_id')->map(fn ($v) => (int) $v)->unique()->values()->all())
            ->toArray();

        $driverTrucks = $pairs
            ->groupBy('driver_id')
            ->map(fn ($rows) => $rows->pluck('truck_id')->map(fn ($v) => (int) $v)->unique()->values()->all())
            ->toArray();

        return [$truckDrivers, $driverTrucks];
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
            $this->handleVehiclePhotoUpload($request, $inspection);

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
        [$truckDrivers, $driverTrucks] = $this->buildTruckDriverMaps();

        return Inertia::render('inspections/Edit', [
            'inspection' => $this->serialize($inspection),
            'trucks' => Truck::query()->where('is_active', true)->orderBy('matricule')->get(['id', 'matricule']),
            'drivers' => Driver::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name', 'code']),
            'truckDrivers' => $truckDrivers,
            'driverTrucks' => $driverTrucks,
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
        $this->handleVehiclePhotoUpload($request, $inspection);

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
            'vehicle_photo' => 'nullable|file|mimes:jpg,jpeg,png|max:8192',
            'driver_id' => 'nullable|exists:drivers,id',
            'project_id' => 'nullable|exists:projects,id',
            'activity' => 'nullable|string|max:255',
            'client_name' => 'nullable|string|max:255',
            'field_remarks' => 'nullable|array',
            'field_remarks.*' => 'nullable|string|max:500',
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
        $base = [
            'inspection_date', 'category', 'findings_summary', 'recommendations',
            'driver_id', 'project_id', 'activity', 'client_name', 'field_remarks',
        ];
        if ($isCreate) {
            $base[] = 'truck_id';
        }
        $payload = array_intersect_key(
            $data,
            array_flip(array_merge($base, InspectionChecklist::INSPECTION_FIELDS))
        );
        if (isset($payload['field_remarks']) && is_array($payload['field_remarks'])) {
            $payload['field_remarks'] = array_filter(
                $payload['field_remarks'],
                fn ($v) => is_string($v) && trim($v) !== ''
            );
        }
        return $payload;
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

    private function handleVehiclePhotoUpload(Request $request, InspectionChecklist $inspection): void
    {
        if (!$request->hasFile('vehicle_photo')) {
            return;
        }

        $file = $request->file('vehicle_photo');
        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $name = sprintf('%d-%s.%s', $inspection->id, now()->format('YmdHis'), $ext);

        if ($inspection->vehicle_photo_path && Storage::disk('public')->exists($inspection->vehicle_photo_path)) {
            Storage::disk('public')->delete($inspection->vehicle_photo_path);
        }

        $path = $file->storeAs('inspection-photos', $name, 'public');

        $inspection->update([
            'vehicle_photo_path' => $path,
            'vehicle_photo_filename' => $file->getClientOriginalName(),
        ]);
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
            // Target anyone who can VIEW inspections (HSE Agent + Super Admin + Admin),
            // and exclude the inspector themselves so they don't get notified of their own work.
            $recipients = User::permission('inspection-list')
                ->where('id', '!=', $inspection->inspector_id)
                ->get();

            if ($recipients->isEmpty()) {
                Log::info('No HSE recipients found for inspection notification', [
                    'inspection_id' => $inspection->id,
                ]);
                return;
            }

            // In-app bell (always writes — DB never fails the way SMTP can).
            Notification::send($recipients, new InspectionSubmittedNotification($inspection, ['database']));

            // Email goes only to recipients with a usable address; failures
            // don't roll back the database notification above.
            $mailRecipients = $recipients->filter(fn ($u) => !empty($u->email));
            if ($mailRecipients->isNotEmpty()) {
                try {
                    Notification::send($mailRecipients, new InspectionSubmittedNotification($inspection, ['mail']));
                } catch (\Throwable $e) {
                    Log::error('InspectionSubmittedNotification mail failed', [
                        'inspection_id' => $inspection->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('HSE notification dispatched', [
                'inspection_id' => $inspection->id,
                'recipients_count' => $recipients->count(),
                'mail_recipients_count' => $mailRecipients->count(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('HSE notification dispatch failed', [
                'inspection_id' => $inspection->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function serialize(InspectionChecklist $inspection): array
    {
        $inspection->loadMissing(['driver:id,name', 'project:id,name,code']);
        $vehiclePhotoUrl = $inspection->vehicle_photo_path
            ? Storage::disk('public')->url($inspection->vehicle_photo_path)
            : null;

        $base = [
            'id' => $inspection->id,
            'truck' => $inspection->truck ? ['id' => $inspection->truck->id, 'matricule' => $inspection->truck->matricule] : null,
            'driver_id' => $inspection->driver_id,
            'driver' => $inspection->driver?->only(['id', 'name']),
            'project_id' => $inspection->project_id,
            'project' => $inspection->project?->only(['id', 'name', 'code']),
            'activity' => $inspection->activity,
            'client_name' => $inspection->client_name,
            'inspector' => $inspection->inspector?->name,
            'inspection_date' => $inspection->inspection_date?->format('Y-m-d'),
            'category' => $inspection->category,
            'status' => $inspection->status,
            'findings_summary' => $inspection->findings_summary,
            'recommendations' => $inspection->recommendations,
            'field_remarks' => $inspection->field_remarks ?? [],
            'validator' => $inspection->validator?->name,
            'validated_at' => $inspection->validated_at?->format('d/m/Y H:i'),
            'validation_notes' => $inspection->validation_notes,
            'attachment_url' => $inspection->attachment_url,
            'attachment_filename' => $inspection->attachment_filename,
            'vehicle_photo_url' => $vehiclePhotoUrl,
            'vehicle_photo_filename' => $inspection->vehicle_photo_filename,
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
