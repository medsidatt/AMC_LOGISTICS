<?php

namespace App\Exports;

use App\Models\TransportTracking;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MissingTransportTrackingExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return TransportTracking::query()
            ->whereNull('reference')
            ->orWhere('reference', '')
            ->orWhereRaw("TRIM(SUBSTRING_INDEX(reference, '-', 1)) = ''")
            ->orWhereRaw("TRIM(SUBSTRING_INDEX(reference, '-', -1)) = ''")
            ->orWhereNull('provider_date')
            ->orWhereNull('client_date')
            ->orWhereNull('truck_id')
            ->orWhereNull('driver_id')
            ->orWhereNull('provider_id')
            ->orWhereNull('product')
            ->orWhereNull('provider_gross_weight')
            ->orWhereNull('client_gross_weight')
            ->orWhereNull('provider_net_weight')
            ->orWhereNull('client_net_weight')
            ->orWhereNull('provider_tare_weight')
            ->orWhereNull('client_tare_weight')
            ->get()
            ->map(function ($item) {
                return [
                    'Id'                      => $item->id,
                    'Departure Date'           => $item->provider_date,
                    'Truck Number'             => $item->truck?->matricule,
                    'Supplier'                 => $item->provider?->name,
                    'Product Type'             => $item->product,
                    'Supplier Weighing Ticket' => $item->reference ? explode('-', $item->reference)[0] : null,
                    'Supplier Net Weight'      => $item->provider_net_weight,
                    'Supplier Gross Weight'    => $item->provider_gross_weight,
                    'Supplier Tare'            => $item->provider_tare_weight,
                    'Delivery Date'            => $item->client_date,
                    'Client Weighing Ticket'   => $item->reference ? explode('-', $item->reference)[1] ?? null : null,
                    'Client Net Weight'        => $item->client_net_weight,
                    'Client Gross Weight'      => $item->client_gross_weight,
                    'Client Tare'              => $item->client_tare_weight,
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
            'Supplier Weighing Ticket',
            'Supplier Net Weight',
            'Supplier Gross Weight',
            'Supplier Tare',
            'Delivery Date',
            'Client Weighing Ticket',
            'Client Net Weight',
            'Client Gross Weight',
            'Client Tare',
            'Transporter',
            'Driver',
        ];
    }
}
