<?php

namespace App\Notifications;

use App\Models\InspectionChecklist;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InspectionSubmittedNotification extends Notification
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
        $inspection = $this->inspection->loadMissing(['truck:id,matricule', 'inspector:id,name']);
        $matricule = $inspection->truck?->matricule ?? '—';
        $inspector = $inspection->inspector?->name ?? '—';
        $date = $inspection->inspection_date?->format('d/m/Y') ?? '—';

        return (new MailMessage)
            ->subject("Nouvelle inspection HSE — Camion {$matricule}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Une inspection HSE vient d'être soumise pour le camion **{$matricule}**.")
            ->line("Inspecteur : **{$inspector}**")
            ->line("Date d'inspection : **{$date}**")
            ->action("Consulter l'inspection", route('hse.inspections.show', $inspection->id))
            ->line("Merci de vérifier les éventuels points relevés et de planifier les actions correctives si nécessaire.");
    }

    public function toArray(object $notifiable): array
    {
        $inspection = $this->inspection->loadMissing(['truck:id,matricule', 'inspector:id,name']);

        return [
            'inspection_id' => $inspection->id,
            'truck_id' => $inspection->truck_id,
            'truck_matricule' => $inspection->truck?->matricule,
            'inspector_name' => $inspection->inspector?->name,
            'inspection_date' => $inspection->inspection_date?->format('Y-m-d'),
            'submitted_at' => now()->toIso8601String(),
            'url' => route('hse.inspections.show', $inspection->id),
        ];
    }
}
