<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fiche d'Inspection d'Équipement — #{{ $inspection->id }}</title>
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

        .title-bar { background: #f3f3f3; border: 1px solid #999; padding: 4px 8px; text-align: center; font-weight: bold; font-size: 12px; margin-bottom: 4px; }

        .header-grid { width: 100%; border-collapse: collapse; border: 1px solid #999; margin-bottom: 6px; }
        .header-grid td { vertical-align: top; padding: 6px 8px; }
        .header-grid .info { width: 60%; line-height: 1.5; }
        .header-grid .photo { width: 40%; border-left: 1px solid #999; text-align: center; }
        .header-grid .photo img { max-width: 100%; max-height: 160px; object-fit: contain; }
        .header-grid .photo .placeholder { color: #888; font-style: italic; padding: 30px 0; }

        table.header-info { width: 100%; border-collapse: collapse; }
        table.header-info td { padding: 1px 0; vertical-align: top; line-height: 1.4; font-size: 10px; }
        table.header-info td.label { width: 180px; font-weight: bold; padding-right: 8px; white-space: nowrap; }
        table.header-info td.value { }

        .remark-note { font-size: 9.5px; padding: 4px 0; }
        .remark-note b { font-weight: bold; }

        table.checklist { width: 100%; border-collapse: collapse; margin-top: 2px; }
        table.checklist th, table.checklist td { border: 1px solid #999; padding: 4px 6px; font-size: 9.5px; vertical-align: top; }
        table.checklist thead th { background: #ececec; text-align: left; font-weight: bold; }
        table.checklist .col-no { width: 22px; text-align: center; }
        table.checklist .col-desc { }
        table.checklist .col-status { width: 60px; text-align: center; font-weight: bold; }
        table.checklist .col-remark { width: 30%; }
        .status-ok { color: #064; }
        .status-bad { color: #a00; }
        .status-na { color: #888; }

        .notes-block { margin-top: 8px; font-size: 10px; line-height: 1.4; min-height: 70px; }
        .notes-block .label { font-weight: bold; margin-bottom: 2px; }
        .notes-block .content { white-space: pre-wrap; }

        .signature-row { width: 100%; margin-top: 6px; }
        .signature-row td { vertical-align: top; padding: 6px 0; }
        .signature-row .left { width: 55%; }
        .signature-row .right { width: 45%; text-align: right; font-weight: bold; }
        .signature-block { display: inline-block; padding: 8px 12px 10px 14px; border: 1px solid #e5e7eb; border-left: 3px solid #b91c1c; background: #fffbeb; text-align: left; }
        .signature-block .lbl { font-weight: bold; font-size: 8.5px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 2px; }
        .signature-block .name { font-family: 'Dancing Script', cursive; font-size: 28px; color: #111827; line-height: 1; font-weight: normal; }
        .signature-block .meta { margin-top: 4px; font-size: 8.5px; color: #6b7280; font-weight: normal; }

        .body-esign { margin-top: 8px; padding-top: 5px; border-top: 1px dashed #b91c1c; text-align: center; font-style: italic; font-size: 9px; color: #4b5563; }

        .footer { position: fixed; bottom: 4mm; left: 12mm; right: 12mm; border-top: 1px solid #b00; padding-top: 4px; font-size: 8px; color: #555; }
        .footer table { width: 100%; }
        .footer td { vertical-align: top; padding: 0 4px; }
        .footer .col1 { width: 34%; }
        .footer .col2 { width: 33%; }
        .footer .col3 { width: 33%; text-align: right; }
    </style>
</head>
<body>

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

<div class="title-bar">Fiche d'Inspection d'Équipement</div>

<table class="header-grid">
    <tr>
        <td class="info">
            <table class="header-info">
                {{-- Ligne "Client" masquée — à réactiver si besoin (la valeur reste persistée). --}}
                <tr>
                    <td class="label">Nom et prénom du conducteur&nbsp;:</td>
                    <td class="value">{{ $inspection->driver?->name ?: '—' }}</td>
                </tr>
                <tr>
                    <td class="label">Projet&nbsp;:</td>
                    <td class="value">{{ $projectLabel ?: '—' }}</td>
                </tr>
                <tr>
                    <td class="label">Activité&nbsp;:</td>
                    <td class="value">{{ $inspection->activity ?: 'Livraison de Basalte' }}</td>
                </tr>
                <tr>
                    <td class="label">Checklist N°&nbsp;:</td>
                    <td class="value">{{ $inspection->id }}</td>
                </tr>
                <tr>
                    <td class="label">Date&nbsp;:</td>
                    <td class="value">{{ $inspection->inspection_date?->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <td class="label">Nom et numéro de l'équipement&nbsp;:</td>
                    <td class="value">{{ $inspection->truck?->matricule ?? '—' }}</td>
                </tr>
            </table>
        </td>
        <td class="photo">
            @if ($vehiclePhotoPath)
                <img src="{{ $vehiclePhotoPath }}" alt="Véhicule">
            @else
                <div class="placeholder">Aucune photo du véhicule</div>
            @endif
        </td>
    </tr>
</table>

<div class="remark-note">
    <b>Remarque :</b> Veuillez indiquer <b>Oui</b> ou <b>Non</b> dans la colonne prévue et, en cas de commentaire, l'inscrire dans la colonne (<b>Remarques</b>).
</div>

<table class="checklist">
    <thead>
        <tr>
            <th class="col-no">N°</th>
            <th class="col-desc">Description</th>
            <th class="col-status">Oui / Non</th>
            <th class="col-remark">Remarques</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $i => $row)
            <tr>
                <td class="col-no">{{ $i + 1 }}</td>
                <td class="col-desc">{{ $row['label'] }}</td>
                <td class="col-status {{ $row['status_class'] }}">{{ $row['status_label'] }}</td>
                <td class="col-remark">{{ $row['remark'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

@php
    $isValidated = $inspection->status === 'validated';
    $signerName = $inspection->electronic_signature_name ?? $inspection->validator?->name ?? '—';
    $signedAt = $inspection->validated_at?->format('d/m/Y à H:i') ?? '—';
@endphp

<table class="signature-row">
    <tr>
        <td class="left">
            <div class="notes-block">
                @if ($inspection->findings_summary)
                    <div class="label">Constatations</div>
                    <div class="content">{{ $inspection->findings_summary }}</div>
                @endif
                @if ($inspection->recommendations)
                    <div class="label" style="margin-top:6px;">Recommandations</div>
                    <div class="content">{{ $inspection->recommendations }}</div>
                @endif
            </div>
        </td>
        <td class="right">
            <div style="font-size:9px; color:#6b7280; font-weight:bold; text-transform:uppercase; margin-bottom:3px;">Inspecteur</div>
            <div style="font-weight:normal; color:#111827; margin-bottom:8px;">{{ $inspection->inspector?->name ?? '—' }}</div>

            @if ($isValidated)
                <div class="signature-block">
                    <div class="lbl">Signée par le Responsable Logistique</div>
                    <div class="name">{{ $signerName }}</div>
                    <div class="meta">Le {{ $signedAt }}</div>
                </div>
            @endif
        </td>
    </tr>
</table>

{{-- E-signature line at the bottom of the body --}}
@if ($isValidated)
    <div class="body-esign">
        Ce document est signé électroniquement par
        <b>{{ $signerName }}</b>
        le <b>{{ $signedAt }}</b>.
    </div>
@else
    <div class="body-esign">Ce document est signé électroniquement lorsqu'il est validé.</div>
@endif

<div class="footer">
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
