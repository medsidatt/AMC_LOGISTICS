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
        .brand-accent { height: 2px; background: #b91c1c; margin: 4px 0 10px 0; }

        /* Title bar */
        .title-bar { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .title-bar td { padding: 6px 10px; vertical-align: middle; background: #b91c1c; color: #fff; }
        .title-bar .t-left { font-weight: bold; font-size: 13px; letter-spacing: 0.3px; }
        .title-bar .t-right { text-align: right; font-size: 9px; }

        .status-pill { display: inline-block; padding: 2px 10px; border-radius: 10px; font-weight: bold; font-size: 9px; }
        .pill-pending { background: #fbbf24; color: #78350f; }
        .pill-approved { background: #34d399; color: #064e3b; }

        /* Section header */
        .section { margin-top: 12px; }
        .section-header { border-left: 3px solid #b91c1c; padding: 3px 0 3px 8px; font-weight: bold; font-size: 10.5px; color: #1f2937; margin-bottom: 4px; letter-spacing: 0.2px; }

        /* Info card grid */
        .info-grid { width: 100%; border-collapse: collapse; border: 1px solid #e5e7eb; }
        .info-grid td { padding: 6px 10px; vertical-align: top; border-bottom: 1px solid #f1f5f9; font-size: 10px; }
        .info-grid td.k { width: 22%; color: #6b7280; font-size: 9px; text-transform: uppercase; letter-spacing: 0.2px; font-weight: 600; }
        .info-grid td.v { width: 28%; color: #111827; font-weight: 600; }
        .info-grid tr:last-child td { border-bottom: none; }

        /* Filters chips */
        .chips { width: 100%; }
        .chip { display: inline-block; padding: 3px 9px; margin: 0 4px 0 0; border-radius: 10px; font-size: 9px; font-weight: 600; }
        .chip-on  { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .chip-off { background: #f3f4f6; color: #9ca3af; border: 1px solid #e5e7eb; }

        /* Status row in organes */
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

        /* Footer */
        .footer { position: fixed; bottom: 6mm; left: 12mm; right: 12mm; font-size: 8px; color: #6b7280; }
        .footer .accent { height: 1px; background: #b91c1c; margin-bottom: 4px; }
        .footer .esign { text-align: center; font-style: italic; font-size: 8px; color: #6b7280; margin-bottom: 3px; }
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
    <table class="info-grid">
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
            <td class="v">{{ $maintenance->kilometers_at_maintenance ? number_format((float) $maintenance->kilometers_at_maintenance, 0, ',', ' ') . ' km' : '—' }}</td>
        </tr>
        <tr>
            <td class="k">Déclenchement à</td>
            <td class="v">{{ $maintenance->trigger_km ? number_format((float) $maintenance->trigger_km, 0, ',', ' ') . ' km' : '—' }}</td>
            <td class="k">Intervalle profil</td>
            <td class="v">{{ $maintenance->profile?->interval_km ? number_format((float) $maintenance->profile->interval_km, 0, ',', ' ') . ' km' : '—' }}</td>
        </tr>
    </table>
</div>

{{-- Oil --}}
<div class="section">
    <div class="section-header">Huile moteur</div>
    <table class="info-grid">
        <tr>
            <td class="k">Type d'huile</td>
            <td class="v">{{ $maintenance->oil_type ? (\App\Models\Maintenance::OIL_TYPES[$maintenance->oil_type] ?? $maintenance->oil_type) : '—' }}</td>
            <td class="k">Quantité</td>
            <td class="v">{{ $maintenance->oil_quantity_liters ? number_format((float) $maintenance->oil_quantity_liters, 2, ',', ' ') . ' L' : '—' }}</td>
        </tr>
        <tr>
            <td class="k">Vidange effectuée à</td>
            <td class="v">{{ $maintenance->oil_change_km ? number_format((float) $maintenance->oil_change_km, 0, ',', ' ') . ' km' : '—' }}</td>
            <td class="k">Prochaine vidange à</td>
            <td class="v" style="color:#b91c1c;">{{ $maintenance->next_oil_change_km ? number_format((float) $maintenance->next_oil_change_km, 0, ',', ' ') . ' km' : '—' }}</td>
        </tr>
    </table>
</div>

{{-- Organes --}}
<div class="section">
    <div class="section-header">État des organes mécaniques</div>
    <table class="status-grid">
        <tr>
            <td class="k">Boîte de vitesse</td>
            <td class="v">{{ $maintenance->gearbox_status ?: '—' }}</td>
            <td class="k">Différentiel</td>
            <td class="v">{{ $maintenance->differential_status ?: '—' }}</td>
        </tr>
        <tr>
            <td class="k">Circuit hydraulique</td>
            <td class="v">{{ $maintenance->hydraulic_status ?: '—' }}</td>
            <td class="k">Graissage</td>
            <td class="v">{{ $maintenance->greasing_status ?: '—' }}</td>
        </tr>
        <tr>
            <td class="k">Freins</td>
            <td class="v">{{ $maintenance->brake_status ?: '—' }}</td>
            <td class="k">Liquide de refroidissement</td>
            <td class="v">{{ $maintenance->coolant_status ?: '—' }}</td>
        </tr>
        <tr>
            <td class="k">Batterie</td>
            <td class="v" colspan="3">{{ $maintenance->battery_status ?: '—' }}</td>
        </tr>
    </table>
</div>

{{-- Filters --}}
<div class="section">
    <div class="section-header">Filtres remplacés</div>
    <div class="chips" style="padding: 4px 2px;">
        @foreach (['Huile' => $maintenance->filter_oil_changed, 'Hydraulique' => $maintenance->filter_hydraulic_changed, 'Air' => $maintenance->filter_air_changed, 'Carburant' => $maintenance->filter_fuel_changed] as $label => $on)
            <span class="chip {{ $on ? 'chip-on' : 'chip-off' }}">
                {{ $on ? '✓' : '—' }} {{ $label }}
            </span>
        @endforeach
    </div>
</div>

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

{{-- Footer --}}
<div class="footer">
    <div class="accent"></div>
    @if ($isApproved)
        <div class="esign">
            Ce document est signé électroniquement par
            <b>{{ $signerName }}</b>
            le <b>{{ $signedAt }}</b>.
        </div>
    @else
        <div class="esign">Ce document est signé électroniquement lorsqu'il est approuvé.</div>
    @endif
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
