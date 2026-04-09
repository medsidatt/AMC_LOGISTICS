<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Rapport Suivi Transport</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #333; }
        h1 { font-size: 16px; color: #7367f0; margin-bottom: 5px; }
        .meta { font-size: 8px; color: #888; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #7367f0; color: white; padding: 5px 4px; text-align: left; font-size: 8px; }
        td { padding: 4px; border-bottom: 1px solid #eee; }
        tr:nth-child(even) { background: #f9f9f9; }
        .totals { margin-top: 15px; padding: 10px; background: #f3f2f5; border-radius: 4px; }
        .totals span { margin-right: 20px; font-weight: bold; }
        .negative { color: #ea5455; }
        .positive { color: #28c76f; }
    </style>
</head>
<body>
    <h1>Rapport Suivi Transport</h1>
    <div class="meta">
        Généré le {{ now()->format('d/m/Y H:i') }}
        @if(!empty($filters['from'])) | Du {{ $filters['from'] }} @endif
        @if(!empty($filters['to'])) au {{ $filters['to'] }} @endif
        | {{ $totals['count'] }} rotations
    </div>

    <div class="totals">
        <span>Rotations: {{ number_format($totals['count']) }}</span>
        <span>Poids Fournisseur: {{ number_format($totals['provider_net'], 2) }} T</span>
        <span>Poids Client: {{ number_format($totals['client_net'], 2) }} T</span>
        <span class="{{ $totals['gap'] < 0 ? 'negative' : '' }}">Écart: {{ number_format($totals['gap'], 2) }} T</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>Réf.</th>
                <th>Date Client</th>
                <th>Camion</th>
                <th>Conducteur</th>
                <th>Fournisseur</th>
                <th>Produit</th>
                <th>P. Fourn. Net</th>
                <th>P. Client Net</th>
                <th>Écart</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $row)
            <tr>
                <td>{{ $row['reference'] }}</td>
                <td>{{ $row['date_client'] }}</td>
                <td>{{ $row['camion'] }}</td>
                <td>{{ $row['conducteur'] }}</td>
                <td>{{ $row['fournisseur'] }}</td>
                <td>{{ $row['produit'] }}</td>
                <td>{{ number_format($row['poids_fournisseur_net'] ?? 0, 2) }}</td>
                <td>{{ number_format($row['poids_client_net'] ?? 0, 2) }}</td>
                <td class="{{ ($row['ecart'] ?? 0) < 0 ? 'negative' : '' }}">{{ number_format($row['ecart'] ?? 0, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="meta" style="margin-top: 20px;">
        AMC Logistics — Rapport généré automatiquement
    </div>
</body>
</html>
