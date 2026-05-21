<?php

// PLAN-NOTE (à retirer plus tard) ────────────────────────────────────────────
// Itération actuelle : export xlsx filtré du journal d'activité.
// Prochaines itérations envisagées :
//   - Variante CSV (FromQuery + StreamedResponse) pour très grosses plages.
//   - Onglets multiples (Activité / Connexions / Échecs de connexion).
//   - Colonnes supplémentaires si la couverture s'étend (signataire,
//     attachements SharePoint, contexte HSE).
//   - Garde "audit-log-export" séparée de "audit-log-view" si on bascule
//     vers un vrai système de permissions.
// ─────────────────────────────────────────────────────────────────────────────

namespace App\Exports;

use App\Models\AuditLog;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AuditLogExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths
{
    private const ACTION_LABELS = [
        'created' => 'Création',
        'updated' => 'Modification',
        'deleted' => 'Suppression',
        'restored' => 'Restauration',
        'login' => 'Connexion',
        'logout' => 'Déconnexion',
        'login_failed' => 'Échec connexion',
    ];

    public function __construct(private array $filters = [])
    {
    }

    public function collection()
    {
        $query = AuditLog::query()
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if (!empty($this->filters['user_id'])) {
            $query->where('user_id', $this->filters['user_id']);
        }
        if (!empty($this->filters['action'])) {
            $query->where('action', $this->filters['action']);
        }
        if (!empty($this->filters['subject_type'])) {
            $query->where('subject_type', $this->filters['subject_type']);
        }
        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('user_name', 'like', "%{$search}%")
                  ->orWhere('subject_label', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%");
            });
        }
        if (!empty($this->filters['from'])) {
            $query->whereDate('created_at', '>=', $this->filters['from']);
        }
        if (!empty($this->filters['to'])) {
            $query->whereDate('created_at', '<=', $this->filters['to']);
        }

        return $query->get()->map(fn (AuditLog $l) => [
            'date' => $l->created_at?->format('d/m/Y H:i:s'),
            'utilisateur' => $l->user?->name ?? $l->user_name ?? 'système',
            'email' => $l->user?->email ?? '-',
            'action' => self::ACTION_LABELS[$l->action] ?? $l->action,
            'sujet' => $l->subject_type ? class_basename($l->subject_type) : '-',
            'libelle_sujet' => $l->subject_label ?? '-',
            'sujet_id' => $l->subject_id ?? '-',
            'ip' => $l->ip_address ?? '-',
            'avant' => isset($l->changes['before']) ? json_encode($l->changes['before'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '-',
            'apres' => isset($l->changes['after']) ? json_encode($l->changes['after'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '-',
        ]);
    }

    public function headings(): array
    {
        return ['Date', 'Utilisateur', 'Email', 'Action', 'Sujet', 'Libellé sujet', 'Sujet ID', 'IP', 'Avant', 'Après'];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '28a745']]]];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20,
            'B' => 22,
            'C' => 28,
            'D' => 18,
            'E' => 16,
            'F' => 30,
            'G' => 12,
            'H' => 16,
            'I' => 40,
            'J' => 40,
        ];
    }
}
