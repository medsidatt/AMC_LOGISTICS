<?php

namespace App\Notifications;

use App\Models\Truck;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MaintenanceDueNotification extends Notification
{
    use Queueable;

    public function __construct(public Truck $truck, public array $channels = ['mail', 'database'])
    {
    }

    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $matricule = $this->truck->matricule;
        $currentKm = number_format((float) $this->truck->total_kilometers, 0, ',', ' ');
        $thresholdKm = number_format((float) $this->truck->nextMaintenanceAtKm(), 0, ',', ' ');

        return (new MailMessage)
            ->subject("Maintenance moteur due — Camion {$matricule}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Le camion **{$matricule}** a atteint le seuil de maintenance moteur planifiée.")
            ->line("Kilométrage actuel : **{$currentKm} km**")
            ->line("Seuil planifié : **{$thresholdKm} km**")
            ->action('Voir le tableau de maintenance', route('maintenance.index'))
            ->line('Merci de planifier la maintenance dès que possible.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'truck_id' => $this->truck->id,
            'truck_matricule' => $this->truck->matricule,
            'current_km' => (float) $this->truck->total_kilometers,
            'threshold_km' => (float) $this->truck->nextMaintenanceAtKm(),
            'type' => 'maintenance_due',
            'url' => route('maintenance.index'),
            'notified_at' => now()->toIso8601String(),
        ];
    }
}
