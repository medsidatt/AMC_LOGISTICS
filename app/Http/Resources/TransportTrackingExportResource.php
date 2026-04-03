<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransportTrackingExportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {

        return [
            'id' => $this->id,
            'truck' => $this->truck?->matricule,
            'driver' => $this->driver?->name,
            'provider' => $this->provider?->name,
            'transporter' => $this->truck?->transporter?->name,
            'product' => $this->product,
            'date' => $this->client_date,
            'gap' => $this->gap,
            'gross_weight' => $this->client_gross_weight,
            'net_weight' => $this->client_net_weight,
            'tare_weight' => $this->client_tare_weight
        ];
    }

}
