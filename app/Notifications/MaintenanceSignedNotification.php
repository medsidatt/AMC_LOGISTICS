<?php

namespace App\Notifications;

use App\Models\Maintenance;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MaintenanceSignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Maintenance $maintenance,
        public array $channels = ['database'],
    ) {
    }

    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $m = $this->maintenance->loadMissing(['truck:id,matricule', 'approvedBy:id,name']);
        $matricule = $m->truck?->matricule ?? '—';
        $signer = $m->electronic_signature_name ?? $m->approvedBy?->name ?? '—';
        $date = $m->maintenance_date?->format('d/m/Y') ?? '—';
        $signedAt = $m->approved_at?->format('d/m/Y H:i') ?? '—';

        return (new MailMessage)
            ->subject("Maintenance signée — Camion {$matricule}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Une maintenance vient d'être signée pour le camion **{$matricule}**.")
            ->line("Signée par : **{$signer}** (Responsable Logistique)")
            ->line("Date de maintenance : **{$date}**")
            ->line("Signature électronique : **{$signedAt}**")
            ->action('Consulter la maintenance', route('maintenance.history'))
            ->line('Le document est signé électroniquement et disponible en PDF.');
    }

    public function toArray(object $notifiable): array
    {
        $m = $this->maintenance->loadMissing(['truck:id,matricule', 'approvedBy:id,name']);

        return [
            'maintenance_id'   => $m->id,
            'truck_id'         => $m->truck_id,
            'truck_matricule'  => $m->truck?->matricule,
            'signer_id'        => $m->approved_by_id,
            'signer_name'      => $m->electronic_signature_name ?? $m->approvedBy?->name,
            'maintenance_date' => $m->maintenance_date?->format('Y-m-d'),
            'signed_at'        => $m->approved_at?->toIso8601String(),
            'url'              => route('maintenance.history'),
        ];
    }
}
