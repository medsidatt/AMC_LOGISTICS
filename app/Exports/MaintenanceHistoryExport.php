<?php

namespace App\Exports;

use App\Models\Maintenance;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MaintenanceHistoryExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths
{
    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $query = Maintenance::with(['truck', 'profile'])->orderByDesc('maintenance_date');

        if (!empty($this->filters['truck_id'])) {
            $query->where('truck_id', $this->filters['truck_id']);
        }
        if (!empty($this->filters['maintenance_type'])) {
            $query->where('maintenance_type', $this->filters['maintenance_type']);
        }
        if (!empty($this->filters['from'])) {
            $query->whereDate('maintenance_date', '>=', $this->filters['from']);
        }
        if (!empty($this->filters['to'])) {
            $query->whereDate('maintenance_date', '<=', $this->filters['to']);
        }

        return $query->get()->map(fn ($m) => [
            'date' => $m->maintenance_date?->format('d/m/Y'),
            'camion' => $m->truck?->matricule ?? '-',
            'type' => $m->maintenance_type,
            'km' => round((float) ($m->kilometers_at_maintenance ?? 0), 0),
            'seuil_prevu' => $m->trigger_km ? round((float) $m->trigger_km, 0) : '-',
            'intervalle' => $m->profile?->interval_km ? round($m->profile->interval_km, 0) : '-',
            'notes' => $m->notes ?? '-',
        ]);
    }

    public function headings(): array
    {
        return ['Date', 'Camion', 'Type', 'Km au moment', 'Seuil prévu', 'Intervalle règle', 'Notes'];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '28a745']]]];
    }

    public function columnWidths(): array
    {
        return ['A' => 12, 'B' => 16, 'C' => 14, 'D' => 14, 'E' => 14, 'F' => 14, 'G' => 30];
    }
}
