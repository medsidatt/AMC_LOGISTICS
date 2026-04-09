<?php

namespace App\Exports;

use App\Models\Truck;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FleetReport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths
{
    protected bool $activeOnly;

    public function __construct(bool $activeOnly = true)
    {
        $this->activeOnly = $activeOnly;
    }

    public function collection()
    {
        $query = Truck::with(['transporter', 'maintenanceProfiles' => fn ($q) => $q->active()])
            ->orderBy('matricule');

        if ($this->activeOnly) {
            $query->where('is_active', true);
        }

        return $query->get()->map(function ($truck) {
            $general = $truck->maintenanceProfiles->where('maintenance_type', 'general')->first();

            return [
                'matricule' => $truck->matricule,
                'transporteur' => $truck->transporter?->name ?? '-',
                'compteur_km' => round((float) $truck->total_kilometers, 0),
                'statut' => $truck->is_active ? 'Actif' : 'Inactif',
                'type_maintenance' => $truck->maintenance_type,
                'etat_maintenance' => $general?->status === 'red' ? 'URGENT' : ($general?->status === 'yellow' ? 'Bientôt' : 'OK'),
                'km_restant' => $general ? round(max(0, $general->next_maintenance_km - $truck->total_kilometers), 0) : '-',
                'intervalle_km' => $general?->interval_km ?? '-',
                'derniere_maintenance' => $truck->lastMaintenance()?->maintenance_date?->format('d/m/Y') ?? 'Aucune',
                'fleeti_connecte' => $truck->fleeti_asset_id ? 'Oui' : 'Non',
                'derniere_sync' => $truck->fleeti_last_synced_at?->format('d/m/Y H:i') ?? '-',
            ];
        });
    }

    public function headings(): array
    {
        return ['Matricule', 'Transporteur', 'Compteur (km)', 'Statut', 'Type Maintenance', 'État Maintenance', 'Km Restant', 'Intervalle (km)', 'Dernière Maintenance', 'Fleeti GPS', 'Dernière Sync'];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']]]];
    }

    public function columnWidths(): array
    {
        return ['A' => 16, 'B' => 20, 'C' => 14, 'D' => 10, 'E' => 18, 'F' => 16, 'G' => 12, 'H' => 14, 'I' => 18, 'J' => 12, 'K' => 18];
    }
}
