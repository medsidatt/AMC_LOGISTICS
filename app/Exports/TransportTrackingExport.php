<?php

namespace App\Exports;

use App\Models\TransportTracking;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TransportTrackingExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths
{
    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $query = TransportTracking::with(['truck.transporter', 'driver', 'provider']);

        if (!empty($this->filters['truck_id'])) {
            $query->where('truck_id', $this->filters['truck_id']);
        }
        if (!empty($this->filters['driver_id'])) {
            $query->where('driver_id', $this->filters['driver_id']);
        }
        if (!empty($this->filters['provider_id'])) {
            $query->where('provider_id', $this->filters['provider_id']);
        }
        if (!empty($this->filters['transporter_id'])) {
            $query->whereHas('truck', fn ($q) => $q->where('transporter_id', $this->filters['transporter_id']));
        }
        if (!empty($this->filters['product'])) {
            $query->where('product', $this->filters['product']);
        }
        if (!empty($this->filters['from'])) {
            $query->whereDate('client_date', '>=', $this->filters['from']);
        }
        if (!empty($this->filters['to'])) {
            $query->whereDate('client_date', '<=', $this->filters['to']);
        }

        return $query->orderByDesc('client_date')->get()->map(fn ($t) => [
            'reference' => $t->reference,
            'date_client' => $t->client_date?->format('d/m/Y'),
            'date_fournisseur' => $t->provider_date?->format('d/m/Y'),
            'camion' => $t->truck?->matricule ?? '-',
            'transporteur' => $t->truck?->transporter?->name ?? '-',
            'conducteur' => $t->driver?->name ?? '-',
            'fournisseur' => $t->provider?->name ?? '-',
            'produit' => $t->product,
            'base' => $t->base === 'mr' ? 'Mauritanie' : ($t->base === 'sn' ? 'Sénégal' : $t->base),
            'poids_fournisseur_brut' => $t->provider_gross_weight,
            'poids_fournisseur_tare' => $t->provider_tare_weight,
            'poids_fournisseur_net' => $t->provider_net_weight,
            'poids_client_brut' => $t->client_gross_weight,
            'poids_client_tare' => $t->client_tare_weight,
            'poids_client_net' => $t->client_net_weight,
            'ecart' => $t->gap,
        ]);
    }

    public function headings(): array
    {
        return [
            'Référence', 'Date Client', 'Date Fournisseur', 'Camion', 'Transporteur',
            'Conducteur', 'Fournisseur', 'Produit', 'Base',
            'Poids Fourn. Brut', 'Poids Fourn. Tare', 'Poids Fourn. Net',
            'Poids Client Brut', 'Poids Client Tare', 'Poids Client Net', 'Écart',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '7367f0']]]];
    }

    public function columnWidths(): array
    {
        return ['A' => 14, 'B' => 12, 'C' => 12, 'D' => 14, 'E' => 18, 'F' => 18, 'G' => 18, 'H' => 8, 'I' => 12, 'J' => 14, 'K' => 14, 'L' => 14, 'M' => 14, 'N' => 14, 'O' => 14, 'P' => 10];
    }
}
