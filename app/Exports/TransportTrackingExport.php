<?php

namespace App\Exports;

use App\Models\TransportTracking;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TransportTrackingExport implements FromCollection, WithHeadings
{

    protected mixed $filters;

    public function __construct($filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {

        $transportTrackings = TransportTracking::query();

        // Apply filters
        foreach (['transporter_id', 'truck_id', 'driver_id', 'provider_id'] as $filter) {
            if (isset($this->filters[$filter]) && $this->filters[$filter] !== '') {
                if ($filter === 'transporter_id') {
                    $transportTrackings->whereHas('truck', function ($query) use ($filter) {
                        $query->where('transporter_id', $this->filters[$filter]);
                    });
                } else {
                    $transportTrackings->where($filter, $this->filters[$filter]);
                }
            }
        }

        if (!empty($this->filters['start_date'])) {
            $transportTrackings->whereDate('client_date', '>=', $this->filters['start_date']);
        }

        if (!empty($this->filters['end_date'])) {
            $transportTrackings->whereDate('client_date', '<=', $this->filters['end_date']);
        }

        return $transportTrackings->get()->map(function ($item) {
            return [
                'Id'                      => $item->id,
                'Departure Date'           => $item->provider_date,
                'Truck Number'             => $item->truck?->matricule,
                'Supplier'                 => $item->provider?->name,
                'Product Type'             => $item->product,
//                'Supplier Weighing Ticket' => $item->reference ? explode('-', $item->reference)[0] : null,
                'Supplier Net Weight'      => $item->provider_net_weight,
                'Supplier Gross Weight'    => $item->provider_gross_weight,
                'Supplier Tare'            => $item->provider_tare_weight,
                'Delivery Date'            => $item->client_date,
//                'Client Weighing Ticket'   => $item->reference ? explode('-', $item->reference)[1] ?? null : null,
                'Client Net Weight'        => $item->client_net_weight,
                'Client Gross Weight'      => $item->client_gross_weight,
                'Client Tare'              => $item->client_tare_weight,
                'Gap'                      => $item->gap,
                'Transporter'              => $item->truck?->transporter?->name,
                'Driver'                   => $item->driver?->name,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Id',
            'Departure Date',
            'Truck Number',
            'Supplier',
            'Product Type',
//            'Supplier Weighing Ticket',
            'Supplier Net Weight',
            'Supplier Gross Weight',
            'Supplier Tare',
            'Delivery Date',
//            'Client Weighing Ticket',
            'Client Net Weight',
            'Client Gross Weight',
            'Client Tare',
            'Gap (C.Net - P.Net)',
            'Transporter',
            'Driver',
        ];
    }
}
