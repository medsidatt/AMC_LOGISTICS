<?php

namespace App\Console\Commands;

use App\Models\Auth\User;
use App\Models\DailyChecklist;
use App\Models\DailyChecklistIssue;
use App\Models\Driver;
use App\Models\InspectionChecklist;
use App\Models\InspectionChecklistIssue;
use App\Models\Truck;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SeedInspectionTestData extends Command
{
    protected $signature = 'inspect:seed-test {--with-files : Copy reference PDFs into storage/app/inspections/seed if present}';

    protected $description = 'Seed realistic test data: inspections, weekly checklists and signaled problems';

    public function handle(): int
    {
        $trucks = Truck::query()->where('is_active', true)->limit(8)->get();
        if ($trucks->isEmpty()) {
            $this->error('Aucun camion actif trouvé — créez d\'abord quelques camions.');
            return self::FAILURE;
        }

        $inspector = User::role('Logistics Responsible')->first()
            ?? User::role('Super Admin')->first()
            ?? User::first();
        if (! $inspector) {
            $this->error('Aucun utilisateur disponible comme inspecteur.');
            return self::FAILURE;
        }

        $drivers = Driver::query()->limit(6)->get();
        if ($drivers->isEmpty()) {
            $this->warn('Aucun conducteur trouvé — les checklists hebdomadaires seront sautées.');
        }

        $this->info("Inspector: {$inspector->name}");
        $this->info("Trucks: " . $trucks->pluck('matricule')->implode(', '));

        $pdfPool = $this->option('with-files') ? $this->collectSeedPdfs() : [];
        if ($this->option('with-files')) {
            $this->info("PDF disponibles pour pièce jointe: " . count($pdfPool));
        }

        DB::transaction(function () use ($trucks, $drivers, $inspector, $pdfPool) {
            $this->seedInspections($trucks, $inspector, $pdfPool);
            $this->seedWeeklyChecklists($trucks, $drivers);
            $this->seedSignaledIssues($trucks, $drivers);
        });

        $this->info('✔ Données de test créées avec succès.');
        return self::SUCCESS;
    }

    private function collectSeedPdfs(): array
    {
        $dir = storage_path('app/inspections-seed-input');
        if (! is_dir($dir)) {
            $this->warn("Dossier introuvable: {$dir} — créez-le et déposez vos PDF dedans pour les attacher.");
            return [];
        }
        $files = glob($dir . DIRECTORY_SEPARATOR . '*.pdf') ?: [];
        return array_values($files);
    }

    private function attachPdfToInspection(string $sourcePath, InspectionChecklist $inspection): void
    {
        $targetRel = 'inspections/seed/' . $inspection->id . '-' . basename($sourcePath);
        $targetAbs = storage_path('app/' . $targetRel);
        @mkdir(dirname($targetAbs), 0775, true);
        if (! @copy($sourcePath, $targetAbs)) {
            $this->warn("Impossible de copier " . basename($sourcePath));
            return;
        }
        $inspection->update([
            'attachment_path' => $targetRel,
            'attachment_url' => null,
            'attachment_filename' => basename($sourcePath),
        ]);
    }

    private function seedInspections($trucks, User $inspector, array $pdfPool = []): void
    {
        $bar = $this->output->createProgressBar(count($trucks));
        $this->newLine();
        $this->info('Création des inspections (Fiche d\'Inspection d\'Équipement)...');

        $scenarios = [
            // [ category, findings, recommendations, issues:[ [category, severity, note] ] ]
            [
                'category' => 'comprehensive',
                'findings' => 'Inspection mensuelle complète. Véhicule globalement opérationnel. Problème de phare avant gauche signalé. Usage anormal des pneus dû à un problème de parallélisme.',
                'recommendations' => 'Remplacer le phare avant gauche. Programmer un contrôle géométrie / parallélisme dès le retour à la base.',
                'overrides' => ['lights_full_check' => 'critical', 'tire_tread_depth' => 'needs_attention', 'tire_pressure' => 'needs_attention'],
                'issues' => [
                    ['lights_full_check', 'major', 'Phare avant gauche défaillant'],
                    ['tire_tread_depth', 'minor', 'Usure irrégulière liée au parallélisme'],
                ],
            ],
            [
                'category' => 'safety',
                'findings' => 'Inspection sécurité OK. Extincteur, trousse de secours, gilet et triangles présents et conformes.',
                'recommendations' => 'RAS — maintenir l\'état actuel.',
                'overrides' => [],
                'issues' => [],
            ],
            [
                'category' => 'mechanical',
                'findings' => 'Bruit anormal détecté sur la suspension arrière droite. Vérification recommandée. Niveau d\'huile moteur légèrement bas.',
                'recommendations' => 'Contrôle suspension arrière droite. Compléter le niveau d\'huile moteur.',
                'overrides' => ['suspension' => 'needs_attention', 'oil_level' => 'needs_attention', 'engine_noise' => 'needs_attention'],
                'issues' => [
                    ['suspension', 'major', 'Bruit anormal sur suspension AR droite'],
                    ['oil_level', 'minor', 'Niveau d\'huile en dessous du milieu'],
                ],
            ],
            [
                'category' => 'comprehensive',
                'findings' => 'Fissure mineure constatée sur la benne, côté gauche. Verrouillage benne OK. Reste conforme.',
                'recommendations' => 'Soudure de la fissure à programmer dans les prochains jours. Sans urgence immédiate.',
                'overrides' => ['dump_body_cracks_check' => 'needs_attention', 'dump_body_condition' => 'needs_attention'],
                'issues' => [
                    ['dump_body_cracks_check', 'minor', 'Fissure superficielle benne côté gauche'],
                ],
            ],
            [
                'category' => 'compliance',
                'findings' => 'Documents à bord, permis valide, alarme de recul opérationnelle, gyrophare fonctionnel. Conforme.',
                'recommendations' => 'RAS.',
                'overrides' => [],
                'issues' => [],
            ],
            [
                'category' => 'comprehensive',
                'findings' => 'Fuite hydraulique légère détectée sur flexible benne. Direction présente un jeu anormal.',
                'recommendations' => 'Remplacement flexible hydraulique. Contrôle direction urgent. Immobiliser si aggravation.',
                'overrides' => ['hydraulic_oil_leak' => 'critical', 'hydraulic_hose' => 'critical', 'steering_play' => 'major'],
                'issues' => [
                    ['hydraulic_oil_leak', 'critical', 'Suintement constant — vérifier flexible'],
                    ['steering_play', 'major', 'Jeu anormal dans la direction'],
                ],
            ],
        ];

        foreach ($trucks as $idx => $truck) {
            $scenario = $scenarios[$idx % count($scenarios)];
            $daysAgo = (5 + $idx * 3); // staggered dates: 5,8,11,14,...
            $date = now()->subDays($daysAgo)->startOfDay();

            $fields = [];
            foreach (InspectionChecklist::INSPECTION_FIELDS as $field) {
                $fields[$field] = $scenario['overrides'][$field] ?? 'ok';
            }

            $isOldEnough = $daysAgo > 7;
            $status = $isOldEnough ? InspectionChecklist::STATUS_SUBMITTED : InspectionChecklist::STATUS_SUBMITTED;

            $inspection = InspectionChecklist::create(array_merge(
                $fields,
                [
                    'truck_id' => $truck->id,
                    'inspector_id' => $inspector->id,
                    'inspection_date' => $date,
                    'category' => $scenario['category'],
                    'status' => $status,
                    'findings_summary' => $scenario['findings'],
                    'recommendations' => $scenario['recommendations'],
                ]
            ));

            foreach ($scenario['issues'] as [$category, $severity, $note]) {
                InspectionChecklistIssue::create([
                    'inspection_checklist_id' => $inspection->id,
                    'category' => $category,
                    'flagged' => true,
                    'severity' => $severity,
                    'issue_notes' => $note,
                ]);
            }

            if (! empty($pdfPool)) {
                $pdf = $pdfPool[$idx % count($pdfPool)];
                $this->attachPdfToInspection($pdf, $inspection);
            }

            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
    }

    private function seedWeeklyChecklists($trucks, $drivers): void
    {
        if ($drivers->isEmpty()) {
            return;
        }

        $this->info('Création des checklists hebdomadaires...');

        $weekStart = DailyChecklist::weekStartFor(now())->subWeek();

        foreach ($trucks->take(5) as $idx => $truck) {
            $driver = $drivers[$idx % count($drivers)];
            $variation = $idx % 4;

            $weekStartForRow = $weekStart->copy()->subWeeks(intdiv($idx, 5));
            $checklistDate = $weekStartForRow->copy()->addDays(2); // Wednesday

            // Avoid duplicate for the same truck+week
            $exists = DailyChecklist::query()
                ->where('truck_id', $truck->id)
                ->whereDate('week_start_date', $weekStartForRow->toDateString())
                ->exists();
            if ($exists) {
                continue;
            }

            $defaults = [
                'tire_condition' => 'bon',
                'oil_level' => 'correct',
                'brakes' => 'bon',
                'lights' => 'tous_fonctionnels',
                'general_condition_notes' => 'bon',
            ];

            if ($variation === 1) {
                $defaults['lights'] = 'phare_defaillant';
                $defaults['general_condition_notes'] = 'acceptable';
            } elseif ($variation === 2) {
                $defaults['tire_condition'] = 'use';
                $defaults['oil_level'] = 'bas';
            } elseif ($variation === 3) {
                $defaults['brakes'] = 'mou';
                $defaults['general_condition_notes'] = 'mauvais';
            }

            DailyChecklist::create(array_merge($defaults, [
                'truck_id' => $truck->id,
                'driver_id' => $driver->id,
                'checklist_date' => $checklistDate,
                'week_start_date' => $weekStartForRow,
                'status' => DailyChecklist::STATUS_PENDING,
                'notes' => 'Checklist de test — données générées automatiquement.',
            ]));
        }
    }

    private function seedSignaledIssues($trucks, $drivers): void
    {
        $this->info('Création des signalements de problèmes...');

        $issuePool = [
            ['tires', 'critical', 'tire_1,tire_2', 'Usure sévère pneus avant — à remplacer immédiatement.'],
            ['tires', 'major', 'tire_5,tire_9', 'Pression irrégulière, perte de pression nocturne.'],
            ['tires', 'minor', 'tire_18', 'Coupure superficielle sur le flanc, à surveiller.'],
            ['brakes', 'critical', null, 'Pédale de frein molle, course très longue.'],
            ['brakes', 'major', null, 'Bruit aigu lors du freinage à froid.'],
            ['lights', 'major', 'phare_avant_gauche', 'Phare avant gauche défaillant.'],
            ['lights', 'minor', 'feu_stop', 'Feu stop intermittent.'],
            ['lights', 'critical', 'phare_avant_gauche,phare_avant_droit', 'Aucun phare avant ne fonctionne (conduite nocturne dangereuse).'],
            ['oil', 'minor', null, 'Niveau d\'huile moteur légèrement bas.'],
            ['oil', 'major', null, 'Trace de fuite sous le moteur.'],
            ['fuel', 'minor', null, 'Réserve atteinte plus rapidement que d\'habitude.'],
            ['general', 'major', null, 'Vibrations anormales au-dessus de 60 km/h.'],
            ['general', 'minor', null, 'Bruit de roulement côté droit.'],
            ['general', 'critical', null, 'Fumée blanche à l\'échappement persistante.'],
        ];

        foreach ($trucks->take(6) as $tIdx => $truck) {
            $count = 2 + ($tIdx % 3); // 2-4 issues per truck
            for ($i = 0; $i < $count; $i++) {
                $picked = $issuePool[($tIdx * 3 + $i) % count($issuePool)];
                [$category, $severity, $positions, $note] = $picked;

                $driver = $drivers->isNotEmpty()
                    ? $drivers[($tIdx + $i) % count($drivers)]
                    : null;

                $reportedAt = now()->subDays(($tIdx * 2 + $i))->subHoqurs(rand(1, 23));

                DailyChecklistIssue::create([
                    'truck_id' => $truck->id,
                    'driver_id' => $driver?->id,
                    'daily_checklist_id' => null,
                    'category' => $category,
                    'flagged' => true,
                    'severity' => $severity,
                    'positions' => $positions,
                    'issue_notes' => $note,
                    'reported_at' => $reportedAt,
                ]);
            }
        }
    }
}
