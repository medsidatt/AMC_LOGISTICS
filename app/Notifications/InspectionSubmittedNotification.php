<?php

namespace App\Notifications;

use App\Models\InspectionChecklist;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InspectionSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(public InspectionChecklist $inspection)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
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
