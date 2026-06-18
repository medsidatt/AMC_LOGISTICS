<?php

namespace App\Console\Commands;

use App\Services\RotationAchievementService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class InspectRotationAchievement extends Command
{
    protected $signature = 'rotations:inspect {start} {end}';

    protected $description = 'Dump reconciled rotation achievement (ticket + GPS) for a period';

    public function handle(RotationAchievementService $service): int
    {
        $start = Carbon::parse($this->argument('start'));
        $end = Carbon::parse($this->argument('end'));

        $r = $service->forPeriod($start, $end);
        $f = $r['fleet'];

        $this->info("Période {$r['period']['start']} → {$r['period']['end']}  (GPS dispo: " . ($r['gps_available'] ? 'oui' : 'non') . ", objectif: " . ($r['has_objective'] ? 'oui' : 'non') . ')');
        $this->line("Objectif    : {$f['target_rotations']} rot / {$f['target_tons']} t");
        $this->line("Réalisé     : {$f['done_rotations']} rot / {$f['done_tons']} t  (ticket {$f['ticketed_rotations']} + GPS {$f['gps_only_rotations']})");
        $this->line("Restant     : {$f['remaining_rotations']} rot / {$f['remaining_tons']} t  ({$f['pct']}%)");
        $this->line("Tickets manquants : {$f['missing_tickets']}");
        $p = $r['projection'];
        $this->line("Projection  : {$p['projected_rotations']} rot / {$p['projected_tons']} t  (jour {$p['days_elapsed']}/{$p['days_total']}, " . ($p['on_track'] ? 'en bonne voie' : 'en retard') . ')');

        $this->newLine();
        $this->table(
            ['Camion', 'Obj rot', 'Réa rot', 'Ticket', 'GPS', 'Restant', '%'],
            collect($r['per_truck'])->take(15)->map(fn ($t) => [
                $t['matricule'], $t['target_rotations'], $t['done_rotations'],
                $t['ticketed_rotations'], $t['gps_only_rotations'], $t['remaining_rotations'], $t['pct'] ?? '—',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
