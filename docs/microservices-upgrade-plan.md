# Plan d'Évolution vers une Architecture Distribuée — AMC Logistics

> Document de **discussion** — à valider avant toute décomposition.
> Toute hypothèse marquée `❓` doit être confirmée par Med Sidatt.

---

## 1. Avertissement — lire d'abord

Tu m'as demandé un plan « microservices ». Avant d'écrire ce plan, je dois être honnête sur ce qui rend une architecture distribuée **rentable** ou au contraire **dangereuse** :

| Condition | Statut actuel d'AMC Logistics |
|---|---|
| ≥ 5 développeurs travaillant en parallèle | ❌ 1 développeur (toi) |
| Domaines à scaling indépendant (un service en CPU, l'autre en mémoire) | ⚠️ Pas démontré |
| Équipes différentes avec releases désynchronisées | ❌ Une seule équipe |
| Latence inter-service tolérable (réseau, serialisation) | ⚠️ App de gestion, pas de temps réel critique |
| Infra mature (CI/CD, observabilité, monitoring, runbooks) | ❌ Laragon local + un hébergement |

**Conclusion :** des microservices au sens strict (≥ 4 services indépendants déployables, base de données par service, communication via API/event bus) **ne sont pas le bon outil** pour cette taille d'équipe et ce stade du projet. Ils ajoutent :
- Latence réseau là où il y avait un appel de fonction
- Transactions distribuées (sagas) à la place d'`DB::transaction`
- Schémas désalignés et migrations multi-services
- Logs et erreurs disséminés sans agrégation
- Authentification entre services à gérer
- Déploiements multipliés × N services

**Ce dont tu as réellement besoin** (et que ce plan détaille) c'est probablement :
1. **Du travail asynchrone** (queue workers Redis) — la valeur n°1, gain immédiat
2. **Un monolithe modulaire** — frontières internes claires sans le coût réseau
3. **Une ou deux extractions ciblées** — uniquement quand un domaine *justifie* d'être isolé

Si tu confirmes que tu veux quand même la décomposition totale, on peut, mais ce plan recommande une **trajectoire progressive** : on ne casse rien tant que la douleur ne le justifie pas.

❓ **À confirmer :** quels sont les **besoins urgents** précis qui t'ont fait penser microservices ? Lenteurs ? Échecs en production ? Besoin de scaler ? Volonté de découpler le front du back ? Ce diagnostic change drastiquement la solution.

---

## 2. Diagnostic — où est la douleur aujourd'hui ?

Audit rapide de l'app actuelle :

| Symptôme observable | Cause probable | Solution adaptée |
|---|---|---|
| `QUEUE_CONNECTION=sync` dans `.env.example` | Tout est synchrone : upload SharePoint, sync Fleeti, exports Excel/PDF bloquent la requête HTTP | **Queue workers Redis** (Phase 0) |
| Sync Fleeti déclenchée à la main | Pas de jobs planifiés en arrière-plan | **Scheduler + queue** |
| Imports fuel (EDK, Fleeti) lourds, parfois en timeout | Traitement synchrone d'Excel volumineux | **Queue + chunked import** |
| Export Excel/PDF côté `ReportController` | Génération en pleine requête web | **Queue + notification de fin** |
| Maintenance, optimisation, KPI calculés à la demande | Services PHP appelés en synchrone | **Cache + jobs périodiques** |
| Pages volumineuses (Inertia) chargent tout d'un coup | Pas de pagination/lazy-loading sur certaines vues | **Front, hors scope distribué** |
| Logs erreurs dispersés | Pas d'agrégation centralisée | **Sentry / Telescope** |
| Pas de tests automatisés étendus | Refactor risqué | **Pact + tests d'intégration** |

**Si tu mets de côté 80 % de ces points avec des queues Redis, tu obtiens les bénéfices d'une « architecture moderne » sans casser le monolithe.**

---

## 3. Architecture cible recommandée (en 4 phases)

```
Phase 0 ─── Phase 1 ─── Phase 2 ─────────── Phase 3 (optionnelle)
Queues     Modular     Extraction          Microservices
Redis      Monolith    sélective           complets
1 semaine  2-4 sem.    par service         (probablement jamais)
```

### Phase 0 — Workers asynchrones (1 semaine, gain immédiat)

**Objectif :** rendre asynchrone tout ce qui bloque actuellement les requêtes web.

Configuration :
```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
```

Commandes :
```bash
php artisan queue:work --tries=3 --timeout=300
# en prod : supervisord ou Laravel Horizon
```

Refactor : transformer ces appels synchrones en jobs :

| Service actuel | Job à créer | Priorité |
|---|---|---|
| `FleetiSyncService::syncTruck()` | `App\Jobs\SyncFleetiTruckJob` | Haute |
| `FuelImportController::commitEdk` | `App\Jobs\ImportEdkFuelJob` | Haute |
| `FuelImportController::commitFleeti` | `App\Jobs\ImportFleetiFuelJob` | Haute |
| `ReportController::exportTransportExcel` | `App\Jobs\ExportTransportExcelJob` + email/notification | Haute |
| `SharePointStorageService::upload` (sur gros fichiers) | `App\Jobs\UploadAttachmentJob` | Moyenne |
| `FleetOptimizerService::planWeek` (nouveau) | `App\Jobs\OptimizeWeekJob` | Moyenne |
| `TripSegmentBuilderService::rebuild` | `App\Jobs\RebuildSegmentsJob` | Moyenne |
| `MaintenanceStatusService::recalculate` | `App\Jobs\RecalculateMaintenanceJob` | Basse |

**Bénéfices Phase 0 :**
- Requêtes web < 500 ms même quand un import est en cours
- Possibilité de rejeu (`failed_jobs`)
- Aucune migration de code métier — uniquement déplacer un appel dans `dispatch()`
- Aucun déploiement supplémentaire — c'est le même monolithe

❓ **À confirmer :** est-ce que tu as déjà Redis disponible sur le serveur de prod, ou faut-il l'installer ?

---

### Phase 1 — Monolithe modulaire (2 à 4 semaines)

**Objectif :** réorganiser le code par **domaine métier** sans le déployer séparément. C'est la prep pour pouvoir un jour extraire un service en 1 journée au lieu de 2 mois.

Cartographie des domaines actuels :

```
app/
├── Domain/
│   ├── Fleet/          ← Truck, Driver, Transporter, Maintenance
│   │   ├── Models/
│   │   ├── Services/
│   │   ├── Controllers/
│   │   └── Permissions/
│   ├── Transport/      ← TransportTracking, TripSegment, TruckStop, Place
│   │   ├── Models/
│   │   ├── Services/
│   │   └── Controllers/
│   ├── Telemetry/      ← Fleeti, FuelTracking, KilometerTracking, FuelEvent
│   ├── Inspection/     ← InspectionChecklist, DailyChecklist
│   ├── HSE/
│   ├── Optimization/   ← (nouveau) ClientDemandPlan, TruckAssignment, TruckRestWindow
│   ├── Reporting/      ← Exports, dashboards
│   └── Identity/       ← User, Role, Permission, AuditLog
└── Shared/
    ├── SharePointStorageService
    ├── Notifications
    └── Http/Controller (base)
```

**Règles de découplage interne (à coder en revue, pas en runtime) :**
- Un domaine **ne `use`** un modèle d'un autre domaine que via une *interface* déclarée dans `Shared/Contracts/`
- Toute communication cross-domaine passe par un **event** (`event(new RotationCompleted($id))`) ou un **service injecté**
- Les routes d'un domaine sont dans `routes/domains/{domain}.php`
- Pas de jointure SQL cross-domaine — utiliser un id et hydrater via repository

**Bénéfices Phase 1 :**
- Le jour où tu extrais un domaine en service séparé, le travail est presque fait
- Un nouveau dev peut comprendre un domaine en lisant un seul dossier
- Les tests deviennent ciblables par domaine

**Risque :** churn massif (déplacement de fichiers). À faire **après** que tout passe en queue, sinon tu réécris pendant que la prod bug.

---

### Phase 2 — Extraction sélective (uniquement si justifiée)

**Critère de décision :** un domaine devient un service séparé seulement s'il coche **au moins 2** des cases suivantes :

1. **Charge isolable** : il consomme des ressources fondamentalement différentes (ex. ingestion télémétrie temps réel)
2. **Cycle de vie indépendant** : il est déployé/upgrade à un rythme différent
3. **Réutilisation hors AMC** : un autre client/projet doit pouvoir l'appeler
4. **Stack différente** : Python pour du ML, Node pour du WebSocket, etc.

Candidats classés :

| Domaine | Cases cochées | Recommandation |
|---|---|---|
| **Telemetry (Fleeti ingestion)** | 1 (charge isolable), 4 potentielle (Python si tu fais de l'analytique) | **Candidat n°1** si volume Fleeti dépasse 100k records/jour |
| **Reporting/Exports** | 1 (CPU lourd, pic ponctuel) | Candidat si exports > 1 min régulièrement, sinon Phase 0 suffit |
| **Optimization (nouveau)** | 1 (calcul lourd) | Pour l'instant un job, à extraire seulement si tu introduis un solveur lourd (ortools…) |
| **Notifications** | 4 (parfois Node pour WebSocket temps réel) | À extraire seulement si tu veux pousser sur mobile/web |
| **Fleet/Truck CRUD** | aucune | **Ne pas extraire** — c'est le cœur du domaine, doit rester dans le monolithe |
| **Inspection/HSE** | aucune | Pas extraire |
| **Identity/Auth** | (parfois) — sinon SSO Microsoft existant | Tu utilises déjà Microsoft OAuth ; pas de raison d'extraire |

**Topologie cible v2 réaliste (si Phase 2 démarre) :**

```
┌─────────────────────────────────┐         ┌──────────────────────────┐
│  AMC Logistics Monolith          │ ←HTTP→ │  Telemetry Ingestor       │
│  (Laravel + Inertia/React)       │         │  (Laravel queue worker    │
│  - Fleet                         │         │   ou Python FastAPI)      │
│  - Transport                     │         │  Consomme : Fleeti API    │
│  - Optimization (jobs)           │         │  Produit : events Redis   │
│  - Inspection                    │         │  → fleeti_daily_records   │
│  - HSE                           │         └──────────────────────────┘
│  - Reporting (jobs)              │
│  - Notifications                 │
└────────────┬────────────────────┘
             │ Redis (queues + events)
             ▼
        ┌─────────┐
        │  MySQL  │  ← une seule DB, accédée par les 2 services en lecture/écriture *contrôlée*
        └─────────┘
```

**Bénéfices Phase 2 :**
- Le worker télémétrie peut être un container séparé qu'on scale horizontalement
- Si Fleeti casse, le monolithe continue de servir l'UI
- Pas (encore) de duplication de base de données → on évite les transactions distribuées

❓ **À confirmer :** quel volume Fleeti aujourd'hui ? (records/jour, camions/Fleeti devices) — c'est le chiffre qui décide.

---

### Phase 3 — Microservices complets (probablement jamais nécessaire ici)

À envisager **uniquement si** :
- L'équipe passe à ≥ 4-5 développeurs avec spécialisations
- Le trafic dépasse plusieurs millions de requêtes/jour
- Des contraintes réglementaires imposent l'isolation (PCI, données médicales…)

Architecture théorique :
```
API Gateway (Kong / Traefik)
  ├── fleet-svc       (Laravel + MySQL)
  ├── transport-svc   (Laravel + MySQL)
  ├── telemetry-svc   (Python + TimescaleDB)
  ├── optimization-svc (Python + OR-Tools + cache Redis)
  ├── inspection-svc  (Laravel + MySQL)
  ├── reporting-svc   (Laravel + S3)
  └── identity-svc    (Laravel + Keycloak)

Event bus : Kafka ou RabbitMQ
Observabilité : Grafana + Loki + Prometheus
Déploiement : Kubernetes
```

Coût humain d'aller là sans préparation : **6 à 12 mois à temps plein** pour un seul dev. À garder hors-périmètre tant que les Phases 0-2 ne sont pas saturées.

---

## 4. Trajectoire recommandée (timeline réaliste)

| Mois | Action | Sortie |
|---|---|---|
| **M+1** | Phase 0 — passer 8 jobs critiques en queue Redis | Plus aucune requête web > 1 s |
| **M+2** | Phase 0 (suite) + observabilité (Sentry, Telescope, ou Laravel Pulse) | Erreurs visibles, latence mesurable |
| **M+3** à **M+4** | Phase 1 — réorganiser en `app/Domain/{Fleet,Transport,Telemetry…}` | Code lisible par domaine, prêt pour extraction |
| **M+5** | Tests d'intégration sur chaque domaine | Refactor futur sans peur |
| **M+6+** | Phase 2 — extraire `telemetry-svc` **si et seulement si** le volume Fleeti l'exige | 1 worker séparé pour la télémétrie |
| **M+12+** | Réévaluation : équipe ? trafic ? besoin nouveau ? | Décider Phase 3 ou rester en 2 |

❓ **À confirmer :** quel est ton délai personnel ? Si « urgent » = 1 mois, on s'arrête à Phase 0. Si 6 mois, on fait Phase 0 + 1.

---

## 5. Plan d'action concret pour la Phase 0 (à expédier en premier)

Cette phase est expédiable **maintenant**, sans casser quoi que ce soit. Elle livre 80 % de la valeur ressentie.

### 5.1 Activer Redis comme driver de queue

Fichier : `.env`
```env
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis      # ou predis
```

Sur le serveur prod : démarrer `php artisan queue:work --queue=high,default,low --tries=3 --backoff=30 --timeout=600` via Supervisor ou Horizon.

### 5.2 Créer la table `failed_jobs` (si absente)
```bash
php artisan queue:failed-table
php artisan migrate
```

### 5.3 Lots de migration prioritaires

**Lot Q1 — Sync Fleeti async (1-2 jours)**
- Fichiers : `app/Services/FleetiSyncService.php`, `app/Http/Controllers/API/FleetiSyncController.php`
- À créer : `app/Jobs/SyncFleetiTruckJob.php` (queue=high)
- Le contrôleur ne fait plus que `SyncFleetiTruckJob::dispatch($truckId)` et répond 202 Accepted

**Lot Q2 — Imports fuel async (2 jours)**
- Fichiers : `app/Http/Controllers/FuelImportController.php`
- À créer : `App\Jobs\ImportEdkFuelJob`, `App\Jobs\ImportFleetiFuelJob`
- Pattern : preview reste synchrone, commit devient un job avec progression en base (`fuel_imports` table : status, processed, total, errors_json)
- UI : page d'import affiche barre de progression + notification de fin

**Lot Q3 — Exports async (1-2 jours)**
- Fichiers : `app/Http/Controllers/ReportController.php`, `app/Http/Controllers/TruckController.php` (exportMaintenanceExcel)
- À créer : `App\Jobs\ExportReportJob` générique avec param `report_type`
- Sortie : fichier déposé sur SharePoint ou disque local + notification base de données (cloche Inertia déjà en place)

**Lot Q4 — Optimiseur en queue (1 jour)**
- Fichier : `app/Http/Controllers/FleetOptimizationController.php` méthode `run`
- À créer : `App\Jobs\RunFleetOptimizerJob`
- UI : bouton "Lancer l'optimiseur" passe en "Calcul en cours…" jusqu'à notification de fin

**Lot Q5 — Détection d'événements en queue (2 jours)**
- Fichiers : `app/Services/FuelEventDetectorService.php`, `app/Services/RouteDeviationDetector.php`, `app/Services/UnauthorizedStopDetector.php`, `app/Services/StopDetectorService.php`
- Programmer en cron via `app/Console/Kernel.php` (`schedule`) toutes les 30 min

### 5.4 Observabilité minimale (1 jour)

Installer un de ces 3, classés par simplicité :
1. **Laravel Pulse** (gratuit, officiel Laravel, dashboard intégré) — recommandé pour démarrer
2. **Laravel Telescope** (gratuit, profondeur sur requêtes/jobs/exceptions)
3. **Sentry** (payant au-delà de 5k events/mois, agrégation cross-environment)

❓ **À confirmer :** budget pour l'observabilité ? Si 0, Pulse + Telescope.

---

## 6. Fichiers à créer / modifier pour la Phase 0

**À créer :**
- `app/Jobs/SyncFleetiTruckJob.php`
- `app/Jobs/ImportEdkFuelJob.php`
- `app/Jobs/ImportFleetiFuelJob.php`
- `app/Jobs/ExportReportJob.php`
- `app/Jobs/RunFleetOptimizerJob.php`
- `database/migrations/2026_XX_XX_create_failed_jobs_table.php` (si absente)
- `database/migrations/2026_XX_XX_create_fuel_imports_table.php` (suivi des imports)
- `app/Models/FuelImport.php`
- `app/Notifications/JobCompletedNotification.php`
- `app/Console/Commands/QueueWatchCommand.php` (optionnel, surveille la queue)

**À modifier :**
- `.env`, `.env.example` — `QUEUE_CONNECTION=redis`
- `app/Http/Controllers/FuelImportController.php` — basculer commit en dispatch
- `app/Http/Controllers/ReportController.php` — basculer exports en dispatch
- `app/Http/Controllers/FleetOptimizationController.php::run` — basculer en dispatch
- `app/Http/Controllers/API/FleetiSyncController.php` — basculer en dispatch
- `app/Console/Kernel.php` — ajouter les schedules cron

---

## 7. Vérification

1. **Smoke test queues** — `php artisan tinker` : `dispatch(new \App\Jobs\TestJob)` ; vérifier qu'il sort en quelques secondes
2. **Test d'export** — Lancer un export grand mois ; vérifier que la page revient en < 1 s
3. **Test d'import** — Importer un fichier EDK de 10k lignes ; vérifier que la progression est visible et que la page ne fige pas
4. **Test rejeu** — Forcer une exception dans un job, vérifier qu'il finit dans `failed_jobs` et qu'on peut le rejouer (`php artisan queue:retry all`)
5. **Test panne Redis** — Couper Redis, vérifier que l'app web reste utilisable (les commits redeviennent synchrones via fallback `database` ou affichent un message d'erreur propre)

---

## 8. Hypothèses à valider avant d'écrire du code

1. ❓ Qu'est-ce qui est précisément « urgent » ? Lenteur ressentie ? Imports en timeout ? Besoin de scale ?
2. ❓ Redis disponible en prod ?
3. ❓ Volume Fleeti par jour (records, requêtes API) ?
4. ❓ L'équipe va-t-elle grossir dans les 6 prochains mois ?
5. ❓ Délai cible : 1 mois (Phase 0), 6 mois (Phase 0+1), 12 mois (Phase 0+1+2) ?
6. ❓ Budget infra/SaaS (Sentry, Horizon UI, observabilité) ?
7. ❓ Volonté réelle d'aller en microservices complets (Phase 3) ou recherche d'asynchrone et de clarté (Phases 0-2) ?

---

## 9. Anti-patterns à éviter

- **Microservice mauvais** : un service par table SQL — produit 30 services qui s'appellent en chaîne sur chaque requête
- **Base partagée par tous les services** : si tu extrais un service, sa DB doit être à lui (ou des vues read-only) ; sinon ce n'est pas un microservice
- **Couplage temporel** : si le service A doit attendre B qui attend C, tu as juste reproduit le monolithe avec du réseau en plus
- **Pas de tests** : décomposer sans tests d'intégration = casser la prod chaque sprint
- **Réécrire au lieu de refactoriser** : laisser l'ancien code en place « pour plus tard » double la charge de maintenance
- **Suivre la mode** : Netflix a 1000 services ; AMC en a besoin d'1 à 3 maximum
