<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Rapport de Maintenance - Camions</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #333;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #4472C4;
            padding-bottom: 15px;
        }
        
        .header h1 {
            color: #4472C4;
            font-size: 22px;
            margin-bottom: 5px;
        }
        
        .header .subtitle {
            color: #666;
            font-size: 12px;
        }
        
        .header .date {
            color: #888;
            font-size: 10px;
            margin-top: 5px;
        }
        
        .summary {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .summary h3 {
            color: #4472C4;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .summary-grid {
            display: table;
            width: 100%;
        }
        
        .summary-item {
            display: table-cell;
            text-align: center;
            padding: 10px;
        }
        
        .summary-item .number {
            font-size: 24px;
            font-weight: bold;
        }
        
        .summary-item .label {
            font-size: 10px;
            color: #666;
        }
        
        .danger { color: #dc3545; }
        .warning { color: #ffc107; }
        .success { color: #28a745; }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th {
            background: #4472C4;
            color: white;
            padding: 10px 8px;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
        }
        
        td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
            font-size: 10px;
        }
        
        tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-red {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-yellow {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-green {
            background: #d4edda;
            color: #155724;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            color: #888;
            font-size: 9px;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Rapport de Maintenance des Camions</h1>
        <div class="subtitle">
            <strong>{{ $transporterName ?? 'AMC Travaux SN SARL' }}</strong><br>
            @if($onlyDue)
                Camions nécessitant une maintenance
            @else
                État de maintenance de tous les camions
            @endif
        </div>
        <div class="date">Généré le {{ now()->format('d/m/Y à H:i') }}</div>
    </div>

    <div class="summary">
        <h3>Résumé</h3>
        <table style="margin: 0; border: none;">
            <tr>
                <td style="border: none; text-align: center; width: 25%;">
                    <div class="number">{{ $totalTrucks }}</div>
                    <div class="label">Total Camions</div>
                </td>
                <td style="border: none; text-align: center; width: 25%;">
                    <div class="number danger">{{ $maintenanceDueCount }}</div>
                    <div class="label">Maintenance Requise</div>
                </td>
                <td style="border: none; text-align: center; width: 25%;">
                    <div class="number warning">{{ $warningCount }}</div>
                    <div class="label">Attention</div>
                </td>
                <td style="border: none; text-align: center; width: 25%;">
                    <div class="number success">{{ $okCount }}</div>
                    <div class="label">OK</div>
                </td>
            </tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th>Matricule</th>
                <th>Total Rotations</th>
                <th>Rot. Depuis Maintenance</th>
                <th>Dépassement</th>
                <th>Dernière Maintenance</th>
            </tr>
        </thead>
        <tbody>
            @forelse($trucks as $truck)
                @php
                    $rotationsSinceMaintenance = $truck->rotations_since_maintenance;
                    $depassement = $rotationsSinceMaintenance - 12;
                    $lastMaintenance = $truck->lastMaintenance();
                @endphp
                <tr>
                    <td><strong>{{ $truck->matricule }}</strong></td>
                    <td>{{ $truck->total_rotations ?? 0 }}</td>
                    <td>{{ $rotationsSinceMaintenance }}</td>
                    <td style="color: {{ $depassement >= 0 ? '#dc3545' : '#28a745' }}; font-weight: bold;">
                        {{ $depassement >= 0 ? '+' : '' }}{{ $depassement }}
                    </td>
                    <td>{{ $lastMaintenance?->maintenance_date?->format('d/m/Y') ?? 'Aucune' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center; padding: 20px;">
                        Aucun camion trouvé
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        AMC - Système de Gestion de Maintenance des Camions | Page 1
    </div>
</body>
</html>
