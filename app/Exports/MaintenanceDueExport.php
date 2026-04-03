<?php

namespace App\Exports;

use App\Models\Truck;
use App\Models\Transporter;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MaintenanceDueExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths
{
    protected bool $onlyDue;
    protected string $transporterName = 'AMC Travaux SN SARL';

    public function __construct(bool $onlyDue = true)
    {
        $this->onlyDue = $onlyDue;
    }

    public function collection()
    {
        // Get the transporter ID for "AMC Travaux SN SARL"
        $transporter = Transporter::where('name', 'like', '%' . $this->transporterName . '%')->first();

        $query = Truck::with(['transporter', 'maintenances' => fn($q) => $q->latest('maintenance_date')]);

        // Filter by transporter if found
        if ($transporter) {
            $query->where('transporter_id', $transporter->id);
        }

        // Filter only active trucks and sort by matricule
        $query->where('is_active', true)
              ->orderBy('matricule');

        $trucks = $query->get();

        // Filter only maintenance due if specified
        if ($this->onlyDue) {
            $trucks = $trucks->filter(fn($truck) => $truck->maintenance_type === 'kilometers' ? $truck->km_maintenance_due : $truck->maintenance_due);
        }

        return $trucks->map(function ($truck) {
            $lastMaintenance = $truck->lastMaintenance();
            $isKilometerMaintenance = $truck->maintenance_type === 'kilometers';
            $sinceMaintenance = $isKilometerMaintenance
                ? $truck->km_since_maintenance
                : $truck->rotations_since_maintenance;
            $maxInterval = $isKilometerMaintenance
                ? $truck->kmMaintenanceInterval()
                : Truck::MAX_ROTATIONS_BEFORE_MAINTENANCE;
            $remaining = $isKilometerMaintenance
                ? $truck->remainingKm()
                : $truck->remainingRotations();

            return [
                'matricule' => $truck->matricule,
                'maintenance_type' => $isKilometerMaintenance ? 'Kilometres' : 'Rotations',
                'interval_max' => $maxInterval,
                'counter_since_maintenance' => $sinceMaintenance,
                'remaining_before_due' => $remaining,
                'last_maintenance_date' => $lastMaintenance?->maintenance_date?->format('d/m/Y') ?? 'Aucune',
            ];
        })->values();
    }

    public function headings(): array
    {
        return [
            'Matricule',
            'Type Maintenance',
            'Intervalle Max',
            'Depuis Maintenance',
            'Restant Avant Maintenance',
            'Dernière Maintenance',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Header row styling
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18,  // Matricule
            'B' => 22,  // Type
            'C' => 20,  // Interval max
            'D' => 28,  // Depuis maintenance
            'E' => 22,  // Dernière Maintenance
        ];
    }
}
