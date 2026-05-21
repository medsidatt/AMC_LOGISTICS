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
        $query = TransportTracking::with(['truck', 'driver', 'provider']);

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
        // Canonical date keys (aligned with Index). Keep from/to as fallbacks for any legacy caller.
        $start = $this->filters['start_date'] ?? $this->filters['from'] ?? null;
        $end   = $this->filters['end_date']   ?? $this->filters['to']   ?? null;
        if ($start) {
            $query->whereDate('client_date', '>=', $start);
        }
        if ($end) {
            $query->whereDate('client_date', '<=', $end);
        }

        return $query->orderByDesc('client_date')->get()->map(fn ($t) => [
            'reference'        => $t->reference,
            'date_chargement'  => $t->provider_date?->format('d/m/Y'),
            'date_livraison'   => $t->client_date?->format('d/m/Y'),
            'camion'           => $t->truck?->matricule ?? '-',
            'conducteur'       => $t->driver?->name ?? '-',
            'fournisseur'      => $t->provider?->name ?? '-',
            'produit'          => $t->product,
            'base'             => $t->base === 'mr' ? 'Mauritanie' : ($t->base === 'sn' ? 'Sénégal' : $t->base),
            'poids_chrg_brut'  => $t->provider_gross_weight,
            'poids_chrg_tare'  => $t->provider_tare_weight,
            'poids_chrg_net'   => $t->provider_net_weight,
            'poids_livr_brut'  => $t->client_gross_weight,
            'poids_livr_tare'  => $t->client_tare_weight,
            'poids_livr_net'   => $t->client_net_weight,
            'ecart'            => $t->gap,
        ]);
    }

    public function headings(): array
    {
        return [
            'Référence',
            'Date Chargement',
            'Date Livraison',
            'Camion',
            'Conducteur',
            'Fornisseur',
            'Produit',
            'Base',
            'Poids Chargement Brut (t)',
            'Poids Chargement Tare (t)',
            'Poids Chargement Net (t)',
            'Poids Livraison Brut (t)',
            'Poids Livraison Tare (t)',
            'Poids Livraison Net (t)',
            'Écart (t)',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '7367f0']]]];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14,  // Référence
            'B' => 16,  // Date Chargement
            'C' => 16,  // Date Livraison
            'D' => 14,  // Camion
            'E' => 22,  // Conducteur
            'F' => 22,  // Fournisseur
            'G' => 10,  // Produit
            'H' => 14,  // Base
            'I' => 20,  // Poids Chargement Brut
            'J' => 20,  // Poids Chargement Tare
            'K' => 20,  // Poids Chargement Net
            'L' => 20,  // Poids Livraison Brut
            'M' => 20,  // Poids Livraison Tare
            'N' => 20,  // Poids Livraison Net
            'O' => 12,  // Écart
        ];
    }
}
