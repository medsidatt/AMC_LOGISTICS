<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Déclaration CNSS</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid black; padding: 4px; text-align: center; }
        .header-table td { border: none; }
        .no-border { border: none; }
        .bold { font-weight: bold; }
        .center { text-align: center; }
        .right { text-align: right; }
        .underline { text-decoration: underline; }
    </style>
</head>
<body>

<style>
    body {
        font-family: DejaVu Sans, sans-serif;
        /*font-size: 11px;*/
        margin: 0;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }
    .table th,
    .table td {
        border: 1px solid black;
        padding: 2px 4px;
    }
    .header-table td {
        border: none;
    }
    .no-border {
        border: none;
    }
    .bold {
        font-weight: bold;
    }
    .center {
        text-align: center;
    }
    .right {
        text-align: right;
    }
    .underline {
        text-decoration: underline;
    }
    .small
    {
        font-size: 8px;
    }
    .left {
        text-align: left;
    }
    .border-0 {
        border: 0 !important;
    }
</style>

<table class="header-table">
    <tr>
        <td class="bold left small" style="width: 35%">
            République Islamique de la Mauritanie<br>
            Caisse Nationale de Sécurité Sociale<br>
            BP: 2826 Téléphone 45 29 18 20 Nouakchott
            <table class="table" style="width: 80%; margin-top:10px;">
                <tr>
                    <td class="bold left small" style="width: 40%">
                        Année:
                    </td>
                    <td class="bold right small" style="width: 40%">
                        {{ date('Y') }}
                    </td>
                </tr>
            </table>
        </td>
        <td class="center">
            <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('app-assets/images/logo/cnss-logo.png'))) }}"
                 style="width: 120px;"
                 alt="Logo">
        </td>
        <td style="width: 35%;">
            <table class="table">
                <tr>
                    <td class="bold left small">N° Emp:</td>
                    <td class="center small">{{ 13123333 }}</td>
                </tr>
                <tr>
                    <td class="bold left small">Nom:</td>
                    <td class="center small">{{ 'AMC CONSULTING' }}</td>
                </tr>
                <tr>
                    <td class="bold left small">Adresse:</td>
                    <td class="center small">{{ '' }}</td>
                </tr>
                <tr>
                    <td class="bold left small">N° de Tel:</td>
                    <td class="center small">{{ '' }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table class="table" style="width: 50%; margin-top: -8px; margin-left: 4px;">
    <tr>
        <td style="width: 27%" class="bold center small">Trimestre (2)</td>
        <td class="small bold center">1T</td>
        <td class="small bold center">2T</td>
        <td class="small bold center">3T</td>
        <td class="small bold center">4T</td>
    </tr>
    <tr>
        <td style="width: 27%; border: 0"></td>
        <td class="bold center small"></td>
        <td class="bold center small">x</td>
        <td class="bold center small"></td>
        <td class="bold center small"></td>
    </tr>
</table>

<p class="small-1">(1) Cocher la case correspondante.</p>
<span style="text-transform: uppercase; font-size: 13px; padding-left: 14%;">
    Declaration trimestrielle de renumération et de cotisations
</span>

<div style="margin-left: 0;">
    <table class="table">
        <tr>
            <td style="padding: 0; text-align: left; width: 50%; border: 0">
                <span class="small-1">1-Montant des rémunérations versées au personnel pendant le trimestre</span>
            </td>
            <td class="border-0"></td>
            <td class="border-0"></td>
            <td style="width: 15%" class="right">
               {{ 532000 }}
            </td>
        </tr>
        <tr>
            <td style="padding: 0; text-align: left; width: 80%; border: 0">
                <span class="small-1">(Somme égale au total de la colonne 9 de la liste nominative du personnel)</span>
            </td>
            <td style="border: 0" colspan="3"></td>
        </tr>
        <tr>
            <td style="padding: 0; text-align: center; width: 80%; border: 0; font-size: 13px">
                <strong class="small-1">Calcul des cotisations</strong>
            </td>
            <td style="border: 0" colspan="3"></td>
        </tr>
        <tr>
            <td  style="border: 0" class="left small-1">
                <span class="small-1">Cotisation patronale-rémunérations portées au 1 ci-dessus</span>
            </td>
            <td class="right" style="width: 30%">{{ 532000 }}</td>
            <td style="width: 10%">13%</td>
            <td class="right" style="width: 30%">{{ 532000 * 0.13 }}</td>
        </tr>
        <tr>
            <td  style="border: 0" class="left small-1">
                <span class="small-1">Cotisation ouvrière-rémunérations portées au 1 ci-dessus</span>
            </td>
            <td class="right">{{ 532000 }}</td>
            <td>1%</td>
            <td class="right">{{ 532000 * 0.01 }}</td>
        </tr>
        <tr>
            <td  style="border: 0" class="left small-1">
                <span class="small-1">Cotisation patronale médecine du travail </span>
            </td>
            <td class="right">{{ 532000 }}</td>
            <td>2%</td>
            <td class="right">{{ 532000 * 0.02 }}</td>
        </tr>
        <tr>
            <td  style="border: 0; padding-left: 28%;" class="small-1 left">
                <span class="small-1">Total &nbsp; &nbsp; = </span>
            </td>
            <td class="border-0" style="border: 0"></td>
            <td>Total</td>
            <td class="right">{{ 532000 * 0.16 }}</td>
        </tr>

    </table>
</div>
