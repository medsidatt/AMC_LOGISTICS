<?php

namespace App\Notifications;

use App\Models\InspectionChecklist;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InspectionValidatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public InspectionChecklist $inspection,
        public array $channels = ['database'],
    ) {
    }

    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inspection = $this->inspection->loadMissing(['truck:id,matricule', 'inspector:id,name', 'validator:id,name']);
        $matricule = $inspection->truck?->matricule ?? '—';
        $signer = $inspection->electronic_signature_name ?? $inspection->validator?->name ?? '—';
        $date = $inspection->inspection_date?->format('d/m/Y') ?? '—';
        $signedAt = $inspection->validated_at?->format('d/m/Y H:i') ?? '—';

        return (new MailMessage)
            ->subject("Inspection HSE signée — Camion {$matricule}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("L'inspection HSE du camion **{$matricule}** vient d'être signée électroniquement.")
            ->line("Signée par : **{$signer}**")
            ->line("Date d'inspection : **{$date}**")
            ->line("Signature électronique : **{$signedAt}**")
            ->action("Consulter l'inspection", route('hse.inspections.show', $inspection->id))
            ->line('Le document est signé électroniquement et disponible en PDF.');
    }

    public function toArray(object $notifiable): array
    {
        $inspection = $this->inspection->loadMissing(['truck:id,matricule', 'inspector:id,name', 'validator:id,name']);

        return [
            'inspection_id'   => $inspection->id,
            'truck_id'        => $inspection->truck_id,
            'truck_matricule' => $inspection->truck?->matricule,
            'inspector_name'  => $inspection->inspector?->name,
            'signer_id'       => $inspection->validated_by,
            'signer_name'     => $inspection->electronic_signature_name ?? $inspection->validator?->name,
            'inspection_date' => $inspection->inspection_date?->format('Y-m-d'),
            'validated_at'    => $inspection->validated_at?->toIso8601String(),
            'url'             => route('hse.inspections.show', $inspection->id),
        ];
    }
}
