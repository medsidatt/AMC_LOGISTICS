<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fiche de Maintenance — #{{ $maintenance->id }}</title>
    <style>
        @font-face {
            font-family: 'Dancing Script';
            font-style: normal;
            font-weight: 400;
            src: url('{{ public_path("fonts/dancing-script.ttf") }}') format('truetype');
        }

        @page { margin: 16mm 12mm 18mm 12mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; }

        .brand-row { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .brand-row td { vertical-align: middle; padding: 0; }
        .brand-logo { width: 30%; }
        .brand-logo img { max-height: 55px; }
        .brand-center { width: 32%; text-align: center; }
        .brand-iso { width: 38%; text-align: right; font-size: 7px; line-height: 1.3; color: #555; }
        .brand-iso img { max-height: 55px; max-width: 100%; vertical-align: middle; }
        .brand-iso .iso-badge { display: inline-block; border: 1px solid #999; padding: 2px 6px; font-weight: bold; font-size: 8px; }
        .brand-iso .cert-number { display: block; margin-top: 3px; font-size: 7px; }

        .title-bar { background: #f3f3f3; border: 1px solid #999; padding: 4px 8px; text-align: center; font-weight: bold; font-size: 12px; margin-bottom: 6px; }

        table.info { width: 100%; border-collapse: collapse; border: 1px solid #999; margin-bottom: 6px; }
        table.info td { padding: 4px 8px; vertical-align: top; border-bottom: 1px solid #ddd; }
        table.info td.label { width: 220px; font-weight: bold; background: #fafafa; white-space: nowrap; }
        table.info tr:last-child td { border-bottom: none; }

        .section-title { margin-top: 8px; padding: 3px 6px; background: #ececec; border: 1px solid #ccc; font-weight: bold; font-size: 10px; }

        .status-pill { display: inline-block; padding: 1px 8px; border-radius: 10px; font-weight: bold; font-size: 9px; }
        .pill-pending { background: #fde68a; color: #78350f; }
        .pill-assigned { background: #bfdbfe; color: #1e3a8a; }
        .pill-completed { background: #bbf7d0; color: #065f46; }
        .pill-approved { background: #86efac; color: #064e3b; }

        .filters-grid { width: 100%; border-collapse: collapse; }
        .filters-grid td { padding: 3px 6px; border: 1px solid #ddd; font-size: 9.5px; width: 25%; text-align: center; }
        .check-ok { color: #064e3b; font-weight: bold; }
        .check-no { color: #888; }

        .signature-block { margin-top: 14px; padding: 8px 10px; border: 1px dashed #999; }
        .signature-block .label { font-weight: bold; font-size: 9px; color: #555; margin-bottom: 2px; }
        .signature-name { font-family: 'Dancing Script', cursive; font-size: 28px; color: #111; line-height: 1; }
        .signature-meta { margin-top: 4px; font-size: 9px; color: #555; }

        .notes-block { padding: 4px 6px; border: 1px solid #ddd; min-height: 40px; font-size: 9.5px; white-space: pre-wrap; }

        .footer { position: fixed; bottom: 4mm; left: 12mm; right: 12mm; border-top: 1px solid #b00; padding-top: 4px; font-size: 8px; color: #555; }
        .footer .esign { text-align: center; font-style: italic; font-size: 8px; color: #666; margin-bottom: 2px; }
        .footer table { width: 100%; }
        .footer td { vertical-align: top; padding: 0 4px; }
        .footer .col1 { width: 34%; }
        .footer .col2 { width: 33%; }
        .footer .col3 { width: 33%; text-align: right; }
    </style>
</head>
<body>

@php
    $status = $maintenance->status ?? 'pending';
    $pillClass = match ($status) {
        'assigned'  => 'pill-assigned',
        'completed' => 'pill-completed',
        'approved'  => 'pill-approved',
        default     => 'pill-pending',
    };
    $statusLabel = match ($status) {
        'assigned'  => 'Assignée',
        'completed' => 'Terminée',
        'approved'  => 'Approuvée',
        default     => 'En attente',
    };
@endphp

<table class="brand-row">
    <tr>
        <td class="brand-logo">
            @if ($logoPath)
                <img src="{{ $logoPath }}" alt="AMC Travaux">
            @else
                <span style="color:#b00; font-weight:bold; font-size:16px;">AMC TRAVAUX</span>
            @endif
        </td>
        <td class="brand-center"></td>
        <td class="brand-iso">
            @if ($isoBadgePath)
                <img src="{{ $isoBadgePath }}" alt="ISO 9001 & 45001 — Bureau Veritas / UKAS">
            @else
                <span class="iso-badge">ISO 9001 / 45001 — BUREAU VERITAS / UKAS</span>
            @endif
            <span class="cert-number">CERTIFICAT N° AFR 20231019 SEN QS AMC</span>
        </td>
    </tr>
</table>

<div class="title-bar">Fiche de Maintenance N° {{ $maintenance->id }}</div>

<table class="info">
    <tr>
        <td class="label">Camion (immatriculation)</td>
        <td>{{ $maintenance->truck?->matricule ?? '—' }}</td>
        <td class="label">Statut</td>
        <td><span class="status-pill {{ $pillClass }}">{{ $statusLabel }}</span></td>
    </tr>
    <tr>
        <td class="label">Date de maintenance</td>
        <td>{{ $maintenance->maintenance_date?->format('d/m/Y') ?? '—' }}</td>
        <td class="label">Type</td>
        <td>{{ ucfirst($maintenance->maintenance_type ?? '—') }}</td>
    </tr>
    <tr>
        <td class="label">Kilométrage à la maintenance</td>
        <td>{{ $maintenance->kilometers_at_maintenance ? number_format((float) $maintenance->kilometers_at_maintenance, 0, ',', ' ') . ' km' : '—' }}</td>
        <td class="label">Déclenchement (km)</td>
        <td>{{ $maintenance->trigger_km ? number_format((float) $maintenance->trigger_km, 0, ',', ' ') . ' km' : '—' }}</td>
    </tr>
    <tr>
        <td class="label">Huile utilisée</td>
        <td>{{ $maintenance->oil_type ? (\App\Models\Maintenance::OIL_TYPES[$maintenance->oil_type] ?? $maintenance->oil_type) : '—' }}</td>
        <td class="label">Quantité (L)</td>
        <td>{{ $maintenance->oil_quantity_liters ? number_format((float) $maintenance->oil_quantity_liters, 2, ',', ' ') . ' L' : '—' }}</td>
    </tr>
    <tr>
        <td class="label">Vidange effectuée à</td>
        <td>{{ $maintenance->oil_change_km ? number_format((float) $maintenance->oil_change_km, 0, ',', ' ') . ' km' : '—' }}</td>
        <td class="label">Prochaine vidange à</td>
        <td>{{ $maintenance->next_oil_change_km ? number_format((float) $maintenance->next_oil_change_km, 0, ',', ' ') . ' km' : '—' }}</td>
    </tr>
</table>

<div class="section-title">État des organes mécaniques</div>
<table class="info">
    <tr>
        <td class="label">Boîte de vitesse</td>
        <td>{{ $maintenance->gearbox_status ?: '—' }}</td>
        <td class="label">Différentiel</td>
        <td>{{ $maintenance->differential_status ?: '—' }}</td>
    </tr>
    <tr>
        <td class="label">Circuit hydraulique</td>
        <td>{{ $maintenance->hydraulic_status ?: '—' }}</td>
        <td class="label">Graissage</td>
        <td>{{ $maintenance->greasing_status ?: '—' }}</td>
    </tr>
    <tr>
        <td class="label">Freins</td>
        <td>{{ $maintenance->brake_status ?: '—' }}</td>
        <td class="label">Liquide de refroidissement</td>
        <td>{{ $maintenance->coolant_status ?: '—' }}</td>
    </tr>
    <tr>
        <td class="label">Batterie</td>
        <td colspan="3">{{ $maintenance->battery_status ?: '—' }}</td>
    </tr>
</table>

<div class="section-title">Filtres remplacés</div>
<table class="filters-grid">
    <tr>
        <td>Huile : <span class="{{ $maintenance->filter_oil_changed ? 'check-ok' : 'check-no' }}">{{ $maintenance->filter_oil_changed ? '✓ Oui' : '— Non' }}</span></td>
        <td>Hydraulique : <span class="{{ $maintenance->filter_hydraulic_changed ? 'check-ok' : 'check-no' }}">{{ $maintenance->filter_hydraulic_changed ? '✓ Oui' : '— Non' }}</span></td>
        <td>Air : <span class="{{ $maintenance->filter_air_changed ? 'check-ok' : 'check-no' }}">{{ $maintenance->filter_air_changed ? '✓ Oui' : '— Non' }}</span></td>
        <td>Carburant : <span class="{{ $maintenance->filter_fuel_changed ? 'check-ok' : 'check-no' }}">{{ $maintenance->filter_fuel_changed ? '✓ Oui' : '— Non' }}</span></td>
    </tr>
</table>

@php
    $dashboardPhotoPath = $maintenance->dashboard_photo_path
        ? storage_path('app/public/' . $maintenance->dashboard_photo_path)
        : null;
    $dashboardPhotoExists = $dashboardPhotoPath && file_exists($dashboardPhotoPath);
@endphp

@if ($dashboardPhotoExists)
    <div class="section-title">Photo du tableau de bord (preuve du kilométrage)</div>
    <div style="text-align:center; padding:6px;">
        <img src="{{ $dashboardPhotoPath }}" alt="Tableau de bord" style="max-width:60%; max-height:180px; border:1px solid #ccc;">
    </div>
@endif

<div class="section-title">Notes</div>
<div class="notes-block">{{ $maintenance->notes ?: '—' }}</div>

@if ($maintenance->assigned_at || $maintenance->assigned_to_name || $maintenance->assignedTo)
    <div class="section-title">Assignation</div>
    <table class="info">
        <tr>
            <td class="label">Assignée à</td>
            <td>{{ $maintenance->assigned_to_name ?? $maintenance->assignedTo?->name ?? '—' }}</td>
            <td class="label">Le</td>
            <td>{{ $maintenance->assigned_at?->format('d/m/Y H:i') ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Assignée par</td>
            <td colspan="3">{{ $maintenance->assignedBy?->name ?? '—' }}</td>
        </tr>
    </table>
@endif

@if ($maintenance->status === 'approved')
    <div class="signature-block">
        <div class="label">Approuvée et signée par le Responsable Logistique</div>
        <div class="signature-name">{{ $maintenance->electronic_signature_name ?? $maintenance->approvedBy?->name ?? '—' }}</div>
        <div class="signature-meta">Le {{ $maintenance->approved_at?->format('d/m/Y à H:i') ?? '—' }}</div>
    </div>
@endif

<div class="footer">
    @if ($maintenance->status === 'approved')
        <div class="esign">
            Ce document est signé électroniquement par
            <b>{{ $maintenance->electronic_signature_name ?? $maintenance->approvedBy?->name ?? '—' }}</b>
            le <b>{{ $maintenance->approved_at?->format('d/m/Y H:i') ?? '—' }}</b>.
        </div>
    @else
        <div class="esign">Ce document est signé électroniquement lorsqu'il est approuvé.</div>
    @endif
    <table>
        <tr>
            <td class="col1"><b style="color:#b00;">AMC Travaux SARL</b><br>BP 7495 — NQT 304</td>
            <td class="col2">Zone Université — Tevragh Zeina<br>Nouakchott — Mauritanie</td>
            <td class="col3">RC : 228 / 97 843<br>contact@amc-travaux.com</td>
        </tr>
    </table>
</div>

</body>
</html>
