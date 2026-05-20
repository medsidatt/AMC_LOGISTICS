<?php

namespace App\Http\Controllers;

use App\Models\InspectionChecklist;
use App\Models\Maintenance;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class HseController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:inspection-list', ['only' => ['index', 'show']]);
    }

    public function index(Request $request)
    {
        $cutoff = now()->subMonths(6)->startOfDay();

        $inspections = InspectionChecklist::query()
            ->with(['truck:id,matricule', 'inspector:id,name', 'validator:id,name'])
            ->withCount('issues')
            ->where('inspection_date', '>=', $cutoff)
            ->orderByDesc('inspection_date')
            ->orderByDesc('id')
            ->get();

        $serializeInspection = fn (InspectionChecklist $i) => [
            'id' => $i->id,
            'inspection_date' => $i->inspection_date?->format('d/m/Y'),
            'truck' => $i->truck ? ['id' => $i->truck->id, 'matricule' => $i->truck->matricule] : null,
            'inspector' => $i->inspector?->name,
            'category' => $i->category,
            'status' => $i->status,
            'issues_count' => $i->issues_count,
            'validator' => $i->validator?->name,
            'validated_at' => $i->validated_at?->format('d/m/Y H:i'),
            'vehicle_photo_url' => $i->vehicle_photo_path
                ? Storage::disk('public')->url($i->vehicle_photo_path)
                : null,
        ];

        $inspectionsByCategory = collect(array_keys(InspectionChecklist::CATEGORY_OPTIONS))
            ->mapWithKeys(fn ($cat) => [$cat => []])
            ->merge(
                $inspections->groupBy('category')
                    ->map(fn ($g) => $g->map($serializeInspection)->values()->toArray())
            )
            ->toArray();

        $maintenance = Maintenance::query()
            ->with(['truck:id,matricule'])
            ->where('maintenance_date', '>=', $cutoff)
            ->orderByDesc('maintenance_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Maintenance $m) => [
                'id' => $m->id,
                'maintenance_date' => $m->maintenance_date?->format('d/m/Y'),
                'truck' => $m->truck ? ['id' => $m->truck->id, 'matricule' => $m->truck->matricule] : null,
                'kilometers_at_maintenance' => $m->kilometers_at_maintenance !== null ? (float) $m->kilometers_at_maintenance : null,
                'oil_type' => $m->oil_type,
                'oil_change_km' => $m->oil_change_km !== null ? (float) $m->oil_change_km : null,
                'next_oil_change_km' => $m->next_oil_change_km !== null ? (float) $m->next_oil_change_km : null,
                'oil_quantity_liters' => $m->oil_quantity_liters !== null ? (float) $m->oil_quantity_liters : null,
                'hydraulic_status' => $m->hydraulic_status,
                'gearbox_status' => $m->gearbox_status,
                'differential_status' => $m->differential_status,
                'greasing_status' => $m->greasing_status,
                'brake_status' => $m->brake_status,
                'coolant_status' => $m->coolant_status,
                'battery_status' => $m->battery_status,
                'filter_oil_changed' => (bool) $m->filter_oil_changed,
                'filter_hydraulic_changed' => (bool) $m->filter_hydraulic_changed,
                'filter_air_changed' => (bool) $m->filter_air_changed,
                'filter_fuel_changed' => (bool) $m->filter_fuel_changed,
                'notes' => $m->notes,
                'status' => $m->status ?? 'pending',
                'signed_by' => $m->electronic_signature_name,
                'approved_at' => $m->approved_at?->format('d/m/Y H:i'),
            ])
            ->values()
            ->toArray();

        return Inertia::render('inspections/Index', [
            'inspectionsByCategory' => $inspectionsByCategory,
            'maintenance' => $maintenance,
            'cutoff' => $cutoff->format('d/m/Y'),
            'options' => [
                'categories' => InspectionChecklist::CATEGORY_OPTIONS,
                'conditions' => InspectionChecklist::CONDITION_OPTIONS,
                'oilTypes' => Maintenance::OIL_TYPES,
            ],
        ]);
    }

    public function show(InspectionChecklist $inspection)
    {
        $inspection->load(['truck:id,matricule', 'inspector:id,name', 'validator:id,name', 'driver:id,name', 'project:id,name,code', 'issues.maintenance']);

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

    public function exportPdf(InspectionChecklist $inspection)
    {
        $inspection->load(['truck:id,matricule', 'inspector:id,name', 'driver:id,name', 'project:id,name,code']);

        $rows = $this->buildPdfRows($inspection);

        $logoPath = $this->absoluteLocalPath(public_path('images/logo.png'));
        $isoBadgePath = $this->resolveCertBadgePath([
            'iso-certification.png',
            'iso-certification.jpg',
            'iso-bureau-veritas.png',
            'iso-bureau-veritas.jpg',
            'bureau-veritas.png',
        ]);

        $vehiclePhotoPath = $inspection->vehicle_photo_path
            ? $this->absoluteLocalPath(storage_path('app/public/' . $inspection->vehicle_photo_path))
            : null;

        $projectLabel = $inspection->project?->name;

        $pdf = Pdf::loadView('pages.inspections.exports.inspection-pdf', [
            'inspection' => $inspection,
            'rows' => $rows,
            'logoPath' => $logoPath,
            'isoBadgePath' => $isoBadgePath,
            'vehiclePhotoPath' => $vehiclePhotoPath,
            'projectLabel' => $projectLabel,
        ])->setPaper('A4', 'portrait');

        $filename = sprintf(
            'inspection-%s-%s.pdf',
            $inspection->truck?->matricule ?? 'NA',
            $inspection->inspection_date?->format('Y-m-d') ?? now()->format('Y-m-d')
        );

        return $pdf->download($filename);
    }

    private function absoluteLocalPath(string $path): ?string
    {
        return file_exists($path) ? $path : null;
    }

    private function resolveCertBadgePath(array $candidates): ?string
    {
        foreach ($candidates as $name) {
            $full = public_path('images/' . $name);
            if (file_exists($full)) {
                return $full;
            }
        }
        return null;
    }

    private function buildPdfRows(InspectionChecklist $inspection): array
    {
        $issuesByField = $inspection->issues->keyBy('category');
        $fieldRemarks = is_array($inspection->field_remarks) ? $inspection->field_remarks : [];

        $mapping = [
            ['label' => "La cabine de l'opérateur est-elle entièrement fermée et fabriquée avec un matériau de qualité ?", 'fields' => ['cabine_fermee']],
            ['label' => "Le véhicule (transporteur) est-il en bon état ?", 'fields' => ['visible_damage_check', 'cleanliness']],
            ['label' => "Les pneus sont-ils exempts de dommages (boulons, fissures, coupures, pression, etc.) ?", 'fields' => ['tire_cuts', 'tire_tread_depth']],
            ['label' => "Les rétroviseurs sont-ils en bon état ?", 'fields' => ['mirrors']],
            ['label' => "Les phares avant, arrière et les indicateurs sont-ils fonctionnels ?", 'fields' => ['lights_full_check']],
            ['label' => "Les essuie-glaces sont-ils en état de marche ?", 'fields' => ['wipers']],
            ['label' => "Le pare-brise et les vitres sont-ils en bon état ?", 'fields' => ['parebrise_vitres']],
            ['label' => "La ceinture de sécurité est-elle disponible et attachée par le chauffeur ?", 'fields' => ['seatbelts']],
            ['label' => "Les vérins hydrauliques et les tuyaux sont-ils en bon état et sans fuite ?", 'fields' => []],
            ['label' => "Le numéro d'immatriculation est-il visible ?", 'fields' => ['immatriculation_visible']],
            ['label' => "Le klaxon avant et arrière est-il fonctionnel ?", 'fields' => ['horn']],
            ['label' => "Un extincteur est-il présent dans la cabine de l'opérateur ?", 'fields' => ['extinguisher_status']],
            ['label' => "L'opérateur dispose-t-il d'un permis valide et approprié ?", 'fields' => []],
            ['label' => "Une trousse de premiers secours est-elle disponible ?", 'fields' => ['first_aid_kit']],
            ['label' => "Les freins normaux et d'urgence (frein à main) sont-ils fonctionnels et opérationnels ?", 'fields' => ['brake_test_result', 'parking_brake']],
        ];

        $rows = [];
        foreach ($mapping as $entry) {
            $worstCond = 'na';
            $remarks = [];
            foreach ($entry['fields'] as $field) {
                $val = $inspection->{$field};
                if ($val) {
                    $worstCond = $this->worseCondition($worstCond, $val);
                }
                if (!empty($fieldRemarks[$field]) && is_string($fieldRemarks[$field])) {
                    $remarks[] = trim($fieldRemarks[$field]);
                }
                if ($issuesByField->has($field)) {
                    $note = trim((string) $issuesByField[$field]->issue_notes);
                    if ($note !== '') {
                        $remarks[] = $note;
                    }
                }
            }

            [$label, $class] = $this->statusFromCondition($worstCond);

            $rows[] = [
                'label' => $entry['label'],
                'status_label' => $label,
                'status_class' => $class,
                'remark' => implode(' / ', array_unique(array_filter($remarks, fn ($r) => $r !== ''))),
            ];
        }

        return $rows;
    }

    private function worseCondition(string $a, string $b): string
    {
        $rank = ['na' => 0, 'ok' => 1, 'needs_attention' => 2, 'critical' => 3];
        return ($rank[$b] ?? 0) > ($rank[$a] ?? 0) ? $b : $a;
    }

    private function statusFromCondition(string $cond): array
    {
        return match ($cond) {
            'ok' => ['OK', 'status-ok'],
            'needs_attention' => ['Non', 'status-bad'],
            'critical' => ['Non', 'status-bad'],
            default => ['—', 'status-na'],
        };
    }

    private function serialize(InspectionChecklist $inspection): array
    {
        $vehiclePhotoUrl = $inspection->vehicle_photo_path
            ? Storage::disk('public')->url($inspection->vehicle_photo_path)
            : null;

        $base = [
            'id' => $inspection->id,
            'truck' => $inspection->truck ? ['id' => $inspection->truck->id, 'matricule' => $inspection->truck->matricule] : null,
            'driver' => $inspection->driver?->only(['id', 'name']),
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
