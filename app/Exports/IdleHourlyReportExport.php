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

        $categoryLabels = [
            'parking' => 'Parking',
            'provider_site' => 'Carrière',
            'client_site' => 'Base client',
            'base' => 'Base / Hub',
            'fuel_station' => 'Station-service',
            'other_place' => 'Zone connue',
            'on_road' => 'Sur route',
        ];

        return $rows->map(fn ($r) => [
            'camion' => $r['truck_matricule'],
            'date' => Carbon::parse($r['date'])->format('d/m/Y'),
            'heure' => sprintf('%02d:00', $r['hour']),
            'minutes_ralenti' => $r['idle_minutes'],
            'lieu' => $r['location_label'],
            'categorie' => $categoryLabels[$r['category']] ?? $r['category'],
            'classification' => $r['classification'],
            'carriere_proche' => $r['nearest_quarry_name'] ?? '-',
            'carriere_km' => $r['nearest_quarry_km'],
            'client_proche' => $r['nearest_client_name'] ?? '-',
            'client_km' => $r['nearest_client_km'],
            'latitude' => $r['latitude'],
            'longitude' => $r['longitude'],
        ]);
    }

    public function headings(): array
    {
        return [
            'Camion', 'Date', 'Heure', 'Minutes ralenti',
            'Lieu', 'Catégorie', 'Classification',
            'Carrière la plus proche', 'Distance carrière (km)',
            'Client le plus proche', 'Distance client (km)',
            'Latitude', 'Longitude',
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
        return [
            'A' => 14, 'B' => 12, 'C' => 8, 'D' => 14,
            'E' => 32, 'F' => 16, 'G' => 16,
            'H' => 22, 'I' => 16,
            'J' => 22, 'K' => 16,
            'L' => 12, 'M' => 12,
        ];
    }
}
