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

        @page { margin: 14mm 12mm 20mm 12mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }

        /* Brand row */
        .brand-row { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        .brand-row td { vertical-align: middle; padding: 0; }
        .brand-logo { width: 30%; }
        .brand-logo img { max-height: 55px; }
        .brand-center { width: 32%; text-align: center; }
        .brand-iso { width: 38%; text-align: right; font-size: 7px; line-height: 1.3; color: #555; }
        .brand-iso img { max-height: 55px; max-width: 100%; vertical-align: middle; }
        .brand-iso .iso-badge { display: inline-block; border: 1px solid #999; padding: 2px 6px; font-weight: bold; font-size: 8px; }
        .brand-iso .cert-number { display: block; margin-top: 3px; font-size: 7px; }
        .brand-accent { height: 2px; background: #b91c1c; margin: 4px 0 8px 0; }

        /* Title bar */
        .title-bar { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .title-bar td { padding: 6px 10px; vertical-align: middle; background: #b91c1c; color: #fff; }
        .title-bar .t-left { font-weight: bold; font-size: 13px; letter-spacing: 0.3px; }
        .title-bar .t-right { text-align: right; font-size: 9px; }

        .status-pill { display: inline-block; padding: 2px 10px; border-radius: 10px; font-weight: bold; font-size: 9px; }
        .pill-pending { background: #fbbf24; color: #78350f; }
        .pill-approved { background: #34d399; color: #064e3b; }

        /* Card-style sections (mirroring the physical Shell Rimula card) */
        .card { border: 1.5px solid #b91c1c; margin-top: 8px; padding: 0; }
        .card-title { background: #b91c1c; color: #fff; padding: 4px 10px; font-weight: bold; font-size: 11px; text-align: center; letter-spacing: 0.4px; }
        .card-body { padding: 6px 10px; }

        /* General info grid */
        .info-grid { width: 100%; border-collapse: collapse; }
        .info-grid td { padding: 5px 8px; vertical-align: top; border-bottom: 1px solid #f1f5f9; font-size: 10px; }
        .info-grid td.k { width: 22%; color: #6b7280; font-size: 9px; text-transform: uppercase; letter-spacing: 0.2px; font-weight: 600; }
        .info-grid td.v { width: 28%; color: #111827; font-weight: 600; }
        .info-grid tr:last-child td { border-bottom: none; }

        /* Oil checklist (mirrors the card's checkbox list) */
        .oil-list { width: 100%; border-collapse: collapse; }
        .oil-list td { padding: 4px 8px; vertical-align: middle; font-size: 10px; border-bottom: 1px solid #fee2e2; }
        .oil-list td.label { color: #1f2937; font-weight: 600; }
        .oil-list td.box { width: 30px; text-align: center; }
        .oil-list .checkbox { display: inline-block; width: 14px; height: 14px; border: 1.5px solid #b91c1c; background: #fff; text-align: center; line-height: 12px; font-weight: bold; color: #b91c1c; font-size: 12px; }
        .oil-list .checkbox.checked { background: #fff; color: #b91c1c; }
        .oil-list tr:last-child td { border-bottom: none; }

        /* OPERATION table */
        .op-table { width: 100%; border-collapse: collapse; }
        .op-table td { padding: 5px 8px; border-bottom: 1px solid #fde68a; font-size: 10px; }
        .op-table td.label { width: 30%; color: #1f2937; font-weight: 600; }
        .op-table td.value { color: #111827; font-weight: bold; text-align: right; padding-right: 14px; font-family: 'Dancing Script', cursive; font-size: 18px; line-height: 1; }
        .op-table td.unit { width: 12%; text-align: right; color: #6b7280; font-size: 9px; font-weight: 600; }
        .op-table tr:last-child td { border-bottom: none; }

        /* FILTRES grid (2x2) */
        .filters-grid { width: 100%; border-collapse: collapse; }
        .filters-grid td { padding: 6px 10px; border: 1px solid #fde68a; width: 50%; font-size: 10px; }
        .filters-grid .flabel { font-weight: 600; color: #1f2937; display: inline-block; min-width: 80px; }
        .filters-grid .fbox { display: inline-block; width: 18px; height: 14px; border: 1.5px solid #b91c1c; background: #fff; text-align: center; line-height: 12px; font-weight: bold; color: #b91c1c; font-size: 12px; margin-left: 8px; vertical-align: middle; }

        /* Prochaine vidange — prominent banner */
        .next-oil { margin-top: 8px; padding: 10px 14px; border: 1.5px solid #b91c1c; background: #fef3c7; text-align: center; }
        .next-oil .label { font-size: 10px; font-weight: bold; color: #92400e; letter-spacing: 0.3px; }
        .next-oil .value { display: inline-block; font-family: 'Dancing Script', cursive; font-size: 26px; color: #b91c1c; margin: 0 8px; line-height: 1; vertical-align: middle; }
        .next-oil .unit { font-size: 11px; font-weight: bold; color: #1f2937; }

        /* Section header (red left accent) */
        .section { margin-top: 12px; }
        .section-header { border-left: 3px solid #b91c1c; padding: 3px 0 3px 8px; font-weight: bold; font-size: 10.5px; color: #1f2937; margin-bottom: 4px; letter-spacing: 0.2px; }

        /* Status grid for autres organes (brakes, coolant, battery) */
        .status-grid { width: 100%; border-collapse: collapse; border: 1px solid #e5e7eb; }
        .status-grid td { padding: 5px 8px; border-right: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; font-size: 9.5px; vertical-align: top; }
        .status-grid td.k { color: #6b7280; font-size: 9px; font-weight: 600; }
        .status-grid td.v { color: #111827; font-weight: 600; }
        .status-grid tr:last-child td { border-bottom: none; }
        .status-grid td:last-child { border-right: none; }

        /* Notes */
        .notes-block { padding: 8px 10px; border: 1px solid #e5e7eb; min-height: 36px; font-size: 10px; white-space: pre-wrap; background: #f9fafb; }

        /* Photo */
        .photo-box { text-align: center; padding: 6px; border: 1px solid #e5e7eb; background: #f9fafb; }
        .photo-box img { max-width: 60%; max-height: 180px; }

        /* Signature */
        .signature-block { margin-top: 14px; padding: 12px 14px 14px 18px; border: 1px solid #e5e7eb; border-left: 3px solid #b91c1c; background: #fffbeb; }
        .signature-block .label { font-weight: bold; font-size: 9px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 4px; }
        .signature-name { font-family: 'Dancing Script', cursive; font-size: 32px; color: #111827; line-height: 1; }
        .signature-meta { margin-top: 6px; font-size: 9px; color: #6b7280; }

        /* Body e-sign line */
        .body-esign { margin-top: 14px; padding-top: 8px; border-top: 1px dashed #b91c1c; text-align: center; font-style: italic; font-size: 9px; color: #4b5563; }

        /* Footer */
        .footer { position: fixed; bottom: 6mm; left: 12mm; right: 12mm; font-size: 8px; color: #6b7280; }
        .footer .accent { height: 1px; background: #b91c1c; margin-bottom: 4px; }
        .footer table { width: 100%; }
        .footer td { vertical-align: top; padding: 0 4px; }
        .footer .col1 { width: 34%; }
        .footer .col2 { width: 33%; text-align: center; }
        .footer .col3 { width: 33%; text-align: right; }
    </style>
</head>
<body>

@php
    $status = $maintenance->status ?? 'pending';
    $isApproved = $status === 'approved';
    $signerName = $maintenance->electronic_signature_name ?? $maintenance->approvedBy?->name ?? '—';
    $signedAt = $maintenance->approved_at?->format('d/m/Y à H:i') ?? '—';

    $dashboardPhotoPath = $maintenance->dashboard_photo_path
        ? storage_path('app/public/' . $maintenance->dashboard_photo_path)
        : null;
    $dashboardPhotoExists = $dashboardPhotoPath && file_exists($dashboardPhotoPath);

    $oilTypes = \App\Models\Maintenance::OIL_TYPES;
    $selectedOil = $maintenance->oil_type;

    $fmtKm = fn ($v) => $v !== null && $v !== '' ? number_format((float) $v, 0, ',', ' ') : '';
@endphp

{{-- Brand row --}}
<table class="brand-row">
    <tr>
        <td class="brand-logo">
            @if ($logoPath)
                <img src="{{ $logoPath }}" alt="AMC Travaux">
            @else
                <span style="color:#b91c1c; font-weight:bold; font-size:16px;">AMC TRAVAUX</span>
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
<div class="brand-accent"></div>

{{-- Title bar --}}
<table class="title-bar">
    <tr>
        <td class="t-left">FICHE DE MAINTENANCE N° {{ $maintenance->id }}</td>
        <td class="t-right">
            <span class="status-pill {{ $isApproved ? 'pill-approved' : 'pill-pending' }}">
                {{ $isApproved ? 'Signée' : 'En attente' }}
            </span>
        </td>
    </tr>
</table>

{{-- General info --}}
<div class="section">
    <div class="section-header">Informations générales</div>
    <table class="info-grid" style="border: 1px solid #e5e7eb;">
        <tr>
            <td class="k">Camion</td>
            <td class="v">{{ $maintenance->truck?->matricule ?? '—' }}</td>
            <td class="k">Date de maintenance</td>
            <td class="v">{{ $maintenance->maintenance_date?->format('d/m/Y') ?? '—' }}</td>
        </tr>
        <tr>
            <td class="k">Type</td>
            <td class="v">{{ ucfirst($maintenance->maintenance_type ?? '—') }}</td>
            <td class="k">Distance actuelle</td>
            <td class="v">{{ $maintenance->kilometers_at_maintenance ? $fmtKm($maintenance->kilometers_at_maintenance) . ' km' : '—' }}</td>
        </tr>
    </table>
</div>

{{-- Oil checklist (mirrors the physical Shell Rimula card) --}}
<div class="card">
    <div class="card-title">TYPE D'HUILE UTILISÉE</div>
    <div class="card-body" style="padding: 0;">
        <table class="oil-list">
            @foreach ($oilTypes as $key => $label)
                @if ($key === 'other') @continue @endif
                <tr>
                    <td class="label">{{ $label }}</td>
                    <td class="box">
                        <span class="checkbox {{ $selectedOil === $key ? 'checked' : '' }}">
                            {{ $selectedOil === $key ? '✗' : '' }}
                        </span>
                    </td>
                </tr>
            @endforeach
            @if ($selectedOil === 'other')
                <tr>
                    <td class="label" style="font-style: italic;">Autre : {{ $maintenance->oil_type_other ?? '—' }}</td>
                    <td class="box"><span class="checkbox checked">✗</span></td>
                </tr>
            @endif
        </table>
    </div>
</div>

{{-- OPERATION table --}}
<div class="card">
    <div class="card-title">OPÉRATION</div>
    <div style="background: #fef3c7; padding: 4px 10px; border-bottom: 1px solid #fde68a; font-size: 10px;">
        <b style="color: #92400e;">DATE :</b>
        <span style="font-family: 'Dancing Script', cursive; font-size: 18px; color: #1f2937; margin-left: 8px;">
            {{ $maintenance->maintenance_date?->format('d/m/y') ?? '—' }}
        </span>
    </div>
    <table class="op-table">
        <tr>
            <td class="label">Vidange moteur</td>
            <td class="value">{{ $fmtKm($maintenance->oil_change_km) }}</td>
            <td class="unit">Km</td>
        </tr>
        <tr>
            <td class="label">Boîte</td>
            <td class="value">{{ $maintenance->gearbox_status ?: '' }}</td>
            <td class="unit">{{ $maintenance->gearbox_status ? '' : 'Km' }}</td>
        </tr>
        <tr>
            <td class="label">Pont</td>
            <td class="value">{{ $maintenance->differential_status ?: '' }}</td>
            <td class="unit">{{ $maintenance->differential_status ? '' : 'Km' }}</td>
        </tr>
        <tr>
            <td class="label">Hydraulique</td>
            <td class="value">{{ $maintenance->hydraulic_status ?: '' }}</td>
            <td class="unit">{{ $maintenance->hydraulic_status ? '' : 'Km' }}</td>
        </tr>
        <tr>
            <td class="label">Graissage</td>
            <td class="value">{{ $maintenance->greasing_status ?: '' }}</td>
            <td class="unit">{{ $maintenance->greasing_status ? '' : 'Km' }}</td>
        </tr>
    </table>
</div>

{{-- FILTRES --}}
<div class="card">
    <div class="card-title">FILTRES</div>
    <table class="filters-grid">
        <tr>
            <td>
                <span class="flabel">Huile</span>
                <span class="fbox">{{ $maintenance->filter_oil_changed ? '✗' : '' }}</span>
            </td>
            <td>
                <span class="flabel">Hydraulique</span>
                <span class="fbox">{{ $maintenance->filter_hydraulic_changed ? '✗' : '' }}</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="flabel">Air</span>
                <span class="fbox">{{ $maintenance->filter_air_changed ? '✗' : '' }}</span>
            </td>
            <td>
                <span class="flabel">Carburant</span>
                <span class="fbox">{{ $maintenance->filter_fuel_changed ? '✗' : '' }}</span>
            </td>
        </tr>
    </table>
</div>

{{-- Prochaine vidange — prominent banner --}}
<div class="next-oil">
    <span class="label">Prochaine vidange moteur à :</span>
    <span class="value">{{ $fmtKm($maintenance->next_oil_change_km) ?: '—' }}</span>
    <span class="unit">Km</span>
</div>

{{-- Autres organes (brakes, coolant, battery, oil quantity) --}}
@if ($maintenance->brake_status || $maintenance->coolant_status || $maintenance->battery_status || $maintenance->oil_quantity_liters)
    <div class="section">
        <div class="section-header">Autres contrôles</div>
        <table class="status-grid">
            <tr>
                <td class="k">Freins</td>
                <td class="v">{{ $maintenance->brake_status ?: '—' }}</td>
                <td class="k">Liquide de refroidissement</td>
                <td class="v">{{ $maintenance->coolant_status ?: '—' }}</td>
            </tr>
            <tr>
                <td class="k">Batterie</td>
                <td class="v">{{ $maintenance->battery_status ?: '—' }}</td>
                <td class="k">Quantité d'huile</td>
                <td class="v">{{ $maintenance->oil_quantity_liters ? number_format((float) $maintenance->oil_quantity_liters, 2, ',', ' ') . ' L' : '—' }}</td>
            </tr>
        </table>
    </div>
@endif

{{-- Notes --}}
@if (!empty($maintenance->notes))
    <div class="section">
        <div class="section-header">Notes</div>
        <div class="notes-block">{{ $maintenance->notes }}</div>
    </div>
@endif

{{-- Dashboard photo --}}
@if ($dashboardPhotoExists)
    <div class="section">
        <div class="section-header">Photo du tableau de bord (preuve du kilométrage)</div>
        <div class="photo-box">
            <img src="{{ $dashboardPhotoPath }}" alt="Tableau de bord">
        </div>
    </div>
@endif

{{-- Signature --}}
@if ($isApproved)
    <div class="signature-block">
        <div class="label">Signée par le Responsable Logistique</div>
        <div class="signature-name">{{ $signerName }}</div>
        <div class="signature-meta">Le {{ $signedAt }}</div>
    </div>
@endif

{{-- E-signature line at the bottom of the body --}}
@if ($isApproved)
    <div class="body-esign">
        Ce document est signé électroniquement par
        <b>{{ $signerName }}</b>
        le <b>{{ $signedAt }}</b>.
    </div>
@else
    <div class="body-esign">Ce document est signé électroniquement lorsqu'il est approuvé.</div>
@endif

{{-- Footer (company info only) --}}
<div class="footer">
    <div class="accent"></div>
    <table>
        <tr>
            <td class="col1"><b style="color:#b91c1c;">AMC Travaux SARL</b><br>BP 7495 — NQT 304</td>
            <td class="col2">Zone Université — Tevragh Zeina<br>Nouakchott — Mauritanie</td>
            <td class="col3">RC : 228 / 97 843<br>contact@amc-travaux.com</td>
        </tr>
    </table>
</div>

</body>
</html>
