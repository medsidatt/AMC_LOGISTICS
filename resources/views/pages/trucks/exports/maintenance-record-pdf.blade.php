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

        @page { margin: 10mm 10mm 14mm 10mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1f2937; line-height: 1.25; }

        /* Brand row */
        .brand-row { width: 100%; border-collapse: collapse; margin-bottom: 2px; }
        .brand-row td { vertical-align: middle; padding: 0; }
        .brand-logo { width: 30%; }
        .brand-logo img { max-height: 42px; }
        .brand-center { width: 32%; text-align: center; }
        .brand-iso { width: 38%; text-align: right; font-size: 6px; line-height: 1.2; color: #555; }
        .brand-iso img { max-height: 42px; max-width: 100%; vertical-align: middle; }
        .brand-iso .iso-badge { display: inline-block; border: 1px solid #999; padding: 1px 4px; font-weight: bold; font-size: 7px; }
        .brand-iso .cert-number { display: block; margin-top: 2px; font-size: 6px; }
        .brand-accent { height: 2px; background: #b91c1c; margin: 2px 0 6px 0; }

        /* Title bar */
        .title-bar { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .title-bar td { padding: 4px 8px; vertical-align: middle; background: #b91c1c; color: #fff; }
        .title-bar .t-left { font-weight: bold; font-size: 11px; letter-spacing: 0.3px; }
        .title-bar .t-right { text-align: right; font-size: 8px; }

        .status-pill { display: inline-block; padding: 1px 8px; border-radius: 8px; font-weight: bold; font-size: 8px; }
        .pill-pending { background: #fbbf24; color: #78350f; }
        .pill-approved { background: #34d399; color: #064e3b; }

        /* Two-column layout */
        .cols { width: 100%; border-collapse: collapse; }
        .cols td.col { width: 50%; vertical-align: top; padding: 0; }
        .cols td.col.left { padding-right: 4px; }
        .cols td.col.right { padding-left: 4px; }

        /* Card-style sections */
        .card { border: 1.2px solid #b91c1c; margin-bottom: 5px; }
        .card-title { background: #b91c1c; color: #fff; padding: 2px 8px; font-weight: bold; font-size: 9px; text-align: center; letter-spacing: 0.3px; }

        /* Section header (red left accent, used for non-card sections) */
        .section-header { border-left: 3px solid #b91c1c; padding: 2px 0 2px 6px; font-weight: bold; font-size: 9px; color: #1f2937; margin: 5px 0 3px 0; letter-spacing: 0.2px; }

        /* General info grid (default 2-pair layout: 38% label / value) */
        .info-grid { width: 100%; border-collapse: collapse; border: 1px solid #e5e7eb; }
        .info-grid td { padding: 3px 6px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; font-size: 9px; }
        .info-grid td.k { width: 38%; color: #6b7280; font-size: 8px; text-transform: uppercase; letter-spacing: 0.2px; font-weight: 600; white-space: nowrap; }
        .info-grid td.v { color: #111827; font-weight: 600; }
        .info-grid tr:last-child td { border-bottom: none; }

        /* Header info row: 3 pairs in one row (Camion / Date / Distance) */
        .info-grid.header-row td.k { width: 9%; }
        .info-grid.header-row td.v { white-space: nowrap; width: 24%; }

        /* Oil checklist */
        .oil-list { width: 100%; border-collapse: collapse; }
        .oil-list td { padding: 2.5px 8px; vertical-align: middle; font-size: 9px; border-bottom: 1px solid #fee2e2; }
        .oil-list td.label { color: #1f2937; font-weight: 600; }
        .oil-list td.box { width: 24px; text-align: center; }
        .oil-list .checkbox { display: inline-block; width: 12px; height: 12px; border: 1.3px solid #b91c1c; background: #fff; text-align: center; line-height: 10px; font-weight: bold; color: #b91c1c; font-size: 10px; }
        .oil-list tr:last-child td { border-bottom: none; }

        /* OPERATION table */
        .op-table { width: 100%; border-collapse: collapse; }
        .op-table td { padding: 3px 6px; border-bottom: 1px solid #fde68a; font-size: 9px; }
        .op-table td.label { width: 35%; color: #1f2937; font-weight: 600; }
        .op-table td.value { color: #111827; font-weight: bold; text-align: right; padding-right: 10px; font-family: 'Dancing Script', cursive; font-size: 15px; line-height: 1; }
        .op-table td.unit { width: 18%; text-align: right; color: #6b7280; font-size: 8px; font-weight: 600; }
        .op-table tr:last-child td { border-bottom: none; }
        .op-date-row { background: #fef3c7; padding: 3px 8px; border-bottom: 1px solid #fde68a; font-size: 9px; }
        .op-date-row .label { color: #92400e; font-weight: bold; }
        .op-date-row .value { font-family: 'Dancing Script', cursive; font-size: 15px; color: #1f2937; margin-left: 6px; }

        /* FILTRES grid */
        .filters-grid { width: 100%; border-collapse: collapse; }
        .filters-grid td { padding: 4px 8px; border: 1px solid #fde68a; width: 50%; font-size: 9px; }
        .filters-grid .flabel { font-weight: 600; color: #1f2937; display: inline-block; min-width: 56px; }
        .filters-grid .fbox { display: inline-block; width: 14px; height: 12px; border: 1.3px solid #b91c1c; background: #fff; text-align: center; line-height: 10px; font-weight: bold; color: #b91c1c; font-size: 10px; margin-left: 6px; vertical-align: middle; }

        /* Prochaine vidange — full-width banner */
        .next-oil { margin: 5px 0; padding: 6px 10px; border: 1.5px solid #b91c1c; background: #fef3c7; text-align: center; }
        .next-oil .label { font-size: 9px; font-weight: bold; color: #92400e; letter-spacing: 0.3px; }
        .next-oil .value { display: inline-block; font-family: 'Dancing Script', cursive; font-size: 22px; color: #b91c1c; margin: 0 6px; line-height: 1; vertical-align: middle; }
        .next-oil .unit { font-size: 10px; font-weight: bold; color: #1f2937; }

        /* Status grid for autres organes */
        .status-grid { width: 100%; border-collapse: collapse; border: 1px solid #e5e7eb; }
        .status-grid td { padding: 3px 6px; border-right: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; font-size: 8.5px; vertical-align: top; }
        .status-grid td.k { color: #6b7280; font-size: 8px; font-weight: 600; }
        .status-grid td.v { color: #111827; font-weight: 600; }
        .status-grid tr:last-child td { border-bottom: none; }
        .status-grid td:last-child { border-right: none; }

        /* Facture items table */
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
        .items-table th { background: #b91c1c; color: #fff; font-size: 8px; text-transform: uppercase; letter-spacing: 0.2px; padding: 3px 6px; text-align: left; }
        .items-table td { padding: 3px 6px; border-bottom: 1px solid #f1f5f9; font-size: 9px; color: #1f2937; vertical-align: top; }
        .items-table th.num, .items-table td.num, .items-table th.qty, .items-table td.qty { text-align: right; }
        .items-table td.d { font-weight: 600; }
        .items-table td.cat { display: block; font-weight: 400; color: #6b7280; font-size: 7.5px; }
        .items-table tfoot td { border-top: 1px solid #e5e7eb; border-bottom: none; }
        .items-table tfoot tr.subtotal td { color: #6b7280; font-weight: 600; font-size: 8.5px; }
        .items-table tfoot tr.grand td { background: #fef3c7; font-weight: bold; font-size: 9.5px; color: #92400e; }

        /* Notes */
        .notes-block { padding: 4px 8px; border: 1px solid #e5e7eb; min-height: 22px; font-size: 9px; white-space: pre-wrap; background: #f9fafb; }

        .signature-block { padding: 6px 10px 8px 12px; border: 1px solid #e5e7eb; border-left: 3px solid #b91c1c; background: #fffbeb; }
        .signature-block .label { font-weight: bold; font-size: 8px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 2px; }
        .signature-name { font-family: 'Dancing Script', cursive; font-size: 26px; color: #111827; line-height: 1; }
        .signature-meta { margin-top: 3px; font-size: 8px; color: #6b7280; }

        /* Body e-sign line */
        .body-esign { margin-top: 6px; padding-top: 4px; border-top: 1px dashed #b91c1c; text-align: center; font-style: italic; font-size: 8px; color: #4b5563; }

        /* Fiche de contrôle (page 2) */
        .fiche-page { page-break-before: always; }
        .fiche-table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .fiche-table th { background: #b91c1c; color: #fff; font-size: 8px; text-transform: uppercase; letter-spacing: 0.2px; padding: 4px 6px; text-align: left; }
        .fiche-table th.c, .fiche-table td.c { text-align: center; width: 64px; }
        .fiche-table td { padding: 4px 6px; border: 1px solid #e5e7eb; font-size: 9px; color: #1f2937; }
        .fiche-table .mark { font-weight: bold; font-size: 11px; color: #b91c1c; }

        /* Footer */
        .footer { position: fixed; bottom: 4mm; left: 10mm; right: 10mm; font-size: 7px; color: #6b7280; }
        .footer .accent { height: 1px; background: #b91c1c; margin-bottom: 2px; }
        .footer table { width: 100%; }
        .footer td { vertical-align: top; padding: 0 3px; }
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

    $oilTypes = \App\Models\Maintenance::OIL_TYPES;
    $selectedOil = $maintenance->oil_type;

    $fmtKm = fn ($v) => $v !== null && $v !== '' ? number_format((float) $v, 0, ',', ' ') : '';

    $hasAutres = $maintenance->brake_status || $maintenance->coolant_status || $maintenance->battery_status || $maintenance->oil_quantity_liters;
@endphp

{{-- Brand row --}}
<table class="brand-row">
    <tr>
        <td class="brand-logo">
            @if ($logoPath)
                <img src="{{ $logoPath }}" alt="AMC Travaux">
            @else
                <span style="color:#b91c1c; font-weight:bold; font-size:14px;">AMC TRAVAUX</span>
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

{{-- General info — header row, three pairs side by side --}}
<table class="info-grid header-row">
    <tr>
        <td class="k">Camion</td>
        <td class="v">{{ $maintenance->truck?->matricule ?? '—' }}</td>
        <td class="k">Date</td>
        <td class="v">{{ $maintenance->maintenance_date?->format('d/m/Y') ?? '—' }}</td>
        <td class="k">Distance</td>
        <td class="v">{{ $maintenance->kilometers_at_maintenance ? $fmtKm($maintenance->kilometers_at_maintenance) . ' km' : '—' }}</td>
    </tr>
</table>

{{-- Two-column dense block: oil checklist + filters | operation table --}}
<table class="cols" style="margin-top:5px;">
    <tr>
        <td class="col left">
            <div class="card">
                <div class="card-title">TYPE D'HUILE UTILISÉE</div>
                <table class="oil-list">
                    @foreach ($oilTypes as $key => $label)
                        @if ($key === 'other') @continue @endif
                        <tr>
                            <td class="label">{{ $label }}</td>
                            <td class="box">
                                <span class="checkbox">{{ $selectedOil === $key ? '✗' : '' }}</span>
                            </td>
                        </tr>
                    @endforeach
                    @if ($selectedOil === 'other')
                        <tr>
                            <td class="label" style="font-style: italic;">Autre</td>
                            <td class="box"><span class="checkbox">✗</span></td>
                        </tr>
                    @endif
                </table>
            </div>

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
        </td>
        <td class="col right">
            <div class="card">
                <div class="card-title">OPÉRATION</div>
                <div class="op-date-row">
                    <span class="label">DATE :</span>
                    <span class="value">{{ $maintenance->maintenance_date?->format('d/m/y') ?? '—' }}</span>
                </div>
                <table class="op-table">
                    <tr>
                        <td class="label">Vidange moteur</td>
                        <td class="value">{{ $fmtKm($maintenance->oil_change_km) }}</td>
                        <td class="unit">Km</td>
                    </tr>
                    <tr>
                        <td class="label">Boîte</td>
                        <td class="value" style="font-size: 9px; font-family: DejaVu Sans, sans-serif;">{{ $maintenance->gearbox_status ?: '' }}</td>
                        <td class="unit">Km</td>
                    </tr>
                    <tr>
                        <td class="label">Pont</td>
                        <td class="value" style="font-size: 9px; font-family: DejaVu Sans, sans-serif;">{{ $maintenance->differential_status ?: '' }}</td>
                        <td class="unit">Km</td>
                    </tr>
                    <tr>
                        <td class="label">Hydraulique</td>
                        <td class="value" style="font-size: 9px; font-family: DejaVu Sans, sans-serif;">{{ $maintenance->hydraulic_status ?: '' }}</td>
                        <td class="unit">Km</td>
                    </tr>
                    <tr>
                        <td class="label">Graissage</td>
                        <td class="value" style="font-size: 9px; font-family: DejaVu Sans, sans-serif;">{{ $maintenance->greasing_status ?: '' }}</td>
                        <td class="unit">Km</td>
                    </tr>
                </table>
            </div>

            @if ($hasAutres)
                <div class="section-header" style="margin-top: 4px;">Autres contrôles</div>
                <table class="status-grid">
                    <tr>
                        <td class="k">Freins</td>
                        <td class="v">{{ $maintenance->brake_status ?: '—' }}</td>
                        <td class="k">Refroidissement</td>
                        <td class="v">{{ $maintenance->coolant_status ?: '—' }}</td>
                    </tr>
                    <tr>
                        <td class="k">Batterie</td>
                        <td class="v">{{ $maintenance->battery_status ?: '—' }}</td>
                        <td class="k">Huile (L)</td>
                        <td class="v">{{ $maintenance->oil_quantity_liters ? number_format((float) $maintenance->oil_quantity_liters, 2, ',', ' ') . ' L' : '—' }}</td>
                    </tr>
                </table>
            @endif
        </td>
    </tr>
</table>

{{-- Prochaine vidange — full-width banner --}}
<div class="next-oil">
    <span class="label">Prochaine vidange moteur à :</span>
    <span class="value">{{ $fmtKm($maintenance->next_oil_change_km) ?: '—' }}</span>
    <span class="unit">Km</span>
</div>

{{-- Facture — custom line items (BON AMC TRAVAUX) --}}
@if ($maintenance->items->isNotEmpty())
    @php
        $itemCategories = \App\Models\MaintenanceItem::CATEGORIES;
        $itemUnits = \App\Models\MaintenanceItem::UNITS;
        $fmtMoney = fn ($v) => number_format((float) $v, 0, ',', ' ');
        $totalsByCat = $maintenance->items->groupBy('category')->map(fn ($g) => $g->sum('line_total'));
        $grandTotal = $maintenance->items->sum('line_total');
    @endphp
    <div class="section-header">Facture — pièces, huile &amp; main d'œuvre</div>
    <table class="items-table">
        <thead>
            <tr>
                <th class="d">Désignation</th>
                <th class="r">Référence</th>
                <th class="qty">Qté / Unité</th>
                <th class="num">Prix U.</th>
                <th class="num">Prix Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($maintenance->items as $item)
                <tr>
                    <td class="d">
                        {{ $item->designation }}
                        <span class="cat">{{ $itemCategories[$item->category] ?? $item->category }}</span>
                    </td>
                    <td class="r">{{ $item->reference ?: '—' }}</td>
                    <td class="qty">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', ' '), '0'), ',') }} {{ $itemUnits[$item->unit] ?? '' }}</td>
                    <td class="num">{{ $fmtMoney($item->unit_price) }}</td>
                    <td class="num">{{ $fmtMoney($item->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            @foreach ($totalsByCat as $cat => $sum)
                <tr class="subtotal">
                    <td colspan="4" class="num">Total {{ $itemCategories[$cat] ?? $cat }}</td>
                    <td class="num">{{ $fmtMoney($sum) }}</td>
                </tr>
            @endforeach
            <tr class="grand">
                <td colspan="4" class="num">TOTAL GÉNÉRAL (FCFA)</td>
                <td class="num">{{ $fmtMoney($grandTotal) }}</td>
            </tr>
        </tfoot>
    </table>
@endif

@if (!empty($maintenance->attachment_filename))
    <div style="font-size: 8px; color: #6b7280; margin-bottom: 5px;">
        Facture jointe : <b>{{ $maintenance->attachment_filename }}</b>
    </div>
@endif

{{-- Notes (only when present, compact) --}}
@if (!empty($maintenance->notes))
    <div class="section-header">Notes</div>
    <div class="notes-block">{{ $maintenance->notes }}</div>
@endif

{{-- Signature --}}
@if ($isApproved)
    <div class="signature-block" style="margin-top: 5px;">
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

{{-- Fiche de contrôle après travaux (page 2) --}}
@php
    $controlChecks = \App\Models\Maintenance::CONTROL_CHECKS;
    $checks = $maintenance->control_checks ?? [];
    $hasChecks = !empty(array_filter(array_intersect_key($checks, $controlChecks)));
@endphp
@if ($hasChecks)
    <div class="fiche-page">
        <table class="title-bar">
            <tr><td class="t-left">FICHE DE CONTRÔLE APRÈS TRAVAUX</td></tr>
        </table>
        <table class="info-grid header-row" style="margin-bottom:6px;">
            <tr>
                <td class="k">Camion</td>
                <td class="v">{{ $maintenance->truck?->matricule ?? '—' }}</td>
                <td class="k">Date</td>
                <td class="v">{{ $maintenance->maintenance_date?->format('d/m/Y') ?? '—' }}</td>
                <td class="k">Distance</td>
                <td class="v">{{ $maintenance->kilometers_at_maintenance ? $fmtKm($maintenance->kilometers_at_maintenance) . ' km' : '—' }}</td>
            </tr>
        </table>
        <table class="fiche-table">
            <thead>
                <tr>
                    <th>Désignation</th>
                    <th class="c">Bon</th>
                    <th class="c">Mauvais</th>
                    <th class="c">N/A</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($controlChecks as $key => $label)
                    <tr>
                        <td>{{ $label }}</td>
                        <td class="c"><span class="mark">{{ ($checks[$key] ?? null) === 'bon' ? '✗' : '' }}</span></td>
                        <td class="c"><span class="mark">{{ ($checks[$key] ?? null) === 'mauvais' ? '✗' : '' }}</span></td>
                        <td class="c"><span class="mark">{{ ($checks[$key] ?? null) === 'na' ? '✗' : '' }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

{{-- Footer (company info only) --}}
<div class="footer">
    <div class="accent"></div>
    <table>
        <tr>
            <td class="col1"><b style="color:#b91c1c;">AMC Travaux SARL</b> — BP 7495 — NQT 304</td>
            <td class="col2">Zone Université — Tevragh Zeina — Nouakchott</td>
            <td class="col3">RC : 228 / 97 843 — contact@amc-travaux.com</td>
        </tr>
    </table>
</div>

</body>
</html>
