<?php

namespace App\Exports;

use App\Services\IdleHourlyReportService;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class IdleHourlyReportExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths
{
    public function __construct(protected array $filters = []) {}

    public function collection()
    {
        $truckIds = array_map('intval', (array) ($this->filters['truck_ids'] ?? []));
        $from = !empty($this->filters['from'])
            ? Carbon::parse($this->filters['from'])->startOfDay()
            : Carbon::now()->subDay()->startOfDay();
        $to = !empty($this->filters['to'])
            ? Carbon::parse($this->filters['to'])->endOfDay()
            : Carbon::now()->endOfDay();

        $rows = app(IdleHourlyReportService::class)->build($truckIds, $from, $to);

        return $rows->map(fn ($r) => [
            'camion' => $r['truck_matricule'],
            'date' => Carbon::parse($r['date'])->format('d/m/Y'),
            'heure' => sprintf('%02d:00', $r['hour']),
            'minutes_ralenti' => $r['idle_minutes'],
            'lieu' => $r['location_label'],
            'classification' => $r['classification'],
            'latitude' => $r['latitude'],
            'longitude' => $r['longitude'],
        ]);
    }

    public function headings(): array
    {
        return [
            'Camion', 'Date', 'Heure', 'Minutes ralenti',
            'Lieu', 'Classification', 'Latitude', 'Longitude',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '7367f0'],
            ],
        ]];
    }

    public function columnWidths(): array
    {
        return ['A' => 14, 'B' => 12, 'C' => 8, 'D' => 14, 'E' => 24, 'F' => 16, 'G' => 12, 'H' => 12];
    }
}
