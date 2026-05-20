<?php

namespace App\Notifications;

use App\Models\Maintenance;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MaintenanceAssignedNotification extends Notification
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
        $m = $this->maintenance->loadMissing(['truck:id,matricule', 'assignedTo:id,name', 'assignedBy:id,name']);
        $matricule = $m->truck?->matricule ?? '—';
        $assignee = $m->assignedTo?->name ?? '—';
        $assigner = $m->assignedBy?->name ?? '—';
        $date = $m->maintenance_date?->format('d/m/Y') ?? '—';

        return (new MailMessage)
            ->subject("Maintenance assignée — Camion {$matricule}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Une maintenance vient d'être assignée pour le camion **{$matricule}**.")
            ->line("Assignée à : **{$assignee}**")
            ->line("Assignée par : **{$assigner}** (Responsable Logistique)")
            ->line("Date de maintenance : **{$date}**")
            ->action('Consulter la maintenance', route('maintenance.history'))
            ->line('Merci de vérifier les détails et de coordonner les actions nécessaires.');
    }

    public function toArray(object $notifiable): array
    {
        $m = $this->maintenance->loadMissing(['truck:id,matricule', 'assignedTo:id,name', 'assignedBy:id,name']);

        return [
            'maintenance_id'   => $m->id,
            'truck_id'         => $m->truck_id,
            'truck_matricule'  => $m->truck?->matricule,
            'assignee_id'      => $m->assigned_to_id,
            'assignee_name'    => $m->assignedTo?->name,
            'assigner_id'      => $m->assigned_by_id,
            'assigner_name'    => $m->assignedBy?->name,
            'maintenance_date' => $m->maintenance_date?->format('Y-m-d'),
            'assigned_at'      => $m->assigned_at?->toIso8601String(),
            'url'              => route('maintenance.history'),
        ];
    }
}
