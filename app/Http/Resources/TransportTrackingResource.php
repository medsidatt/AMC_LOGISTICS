<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransportTrackingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        $provider_reference = null;
        $client_reference = null;

        if (!empty($this->reference)) {
            [$provider_reference, $client_reference] = array_map(
                fn($part) => trim($part),
                explode('-', $this->reference, 2) + [null, null]
            );
        }

        return [
            'id' => $this->id,
            'provider_reference' => $provider_reference,
            'client_reference' => $client_reference,
            'truck' => $this->truck?->matricule,
            'driver' => $this->driver?->name,
            'provider' => $this->provider?->name,
            'transporter' => $this->truck?->transporter?->name,
            'product' => $this->product,
            'provider_date' => $this->provider_date,
            'client_date' => $this->client_date,
            'provider_gross_weight' => $this->provider_gross_weight,
            'provider_net_weight' => $this->provider_net_weight,
            'provider_tare_weight' => $this->provider_tare_weight,
            'gap' => $this->gap,
            'client_gross_weight' => $this->client_gross_weight,
            'client_net_weight' => $this->client_net_weight,
            'client_tare_weight' => $this->client_tare_weight
        ];
    }
}
