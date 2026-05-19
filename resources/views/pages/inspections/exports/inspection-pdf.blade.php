<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fiche d'Inspection d'Équipement — #{{ $inspection->id }}</title>
    <style>
        @page { margin: 16mm 12mm 14mm 12mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; }

        .brand-row { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .brand-row td { vertical-align: middle; padding: 0; }
        .brand-logo { width: 35%; }
        .brand-logo img { max-height: 55px; }
        .brand-center { width: 35%; text-align: center; }
        .brand-iso { width: 30%; text-align: right; font-size: 7px; line-height: 1.3; color: #555; white-space: nowrap; }
        .brand-iso img { height: 42px; vertical-align: middle; margin-left: 4px; }
        .brand-iso .iso-badge { display: inline-block; border: 1px solid #999; padding: 2px 6px; margin-left: 4px; font-weight: bold; font-size: 8px; }
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
        .signature-row .left { width: 60%; }
        .signature-row .right { width: 40%; text-align: right; font-weight: bold; }

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
            @if ($bureauVeritasPath)
                <img src="{{ $bureauVeritasPath }}" alt="ISO 9001 / 14001 / 45001 — Bureau Veritas">
            @else
                <span class="iso-badge">ISO 9001 / 14001 / 45001 — BUREAU VERITAS</span>
            @endif
            @if ($ukasPath)
                <img src="{{ $ukasPath }}" alt="UKAS Management Systems">
            @else
                <span class="iso-badge">UKAS</span>
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
        <td class="right">Inspecteur<br><span style="font-weight:normal;">{{ $inspection->inspector?->name ?? '—' }}</span></td>
    </tr>
</table>

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
