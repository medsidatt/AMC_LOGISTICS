# AMC Logistics

Plateforme de gestion de flotte, transport de basalte et maintenance pour AMC Travaux (Sénégal / Mauritanie).

## Stack technique

| Couche | Technologies |
|--------|-------------|
| Backend | Laravel 12, PHP 8.3 |
| Frontend | React 19, TypeScript, Inertia.js, Tailwind CSS v4 |
| Cartographie | Leaflet + react-leaflet (OpenStreetMap) |
| Graphiques | ApexCharts |
| Base de données | MySQL |
| Télémétrie GPS | Fleeti API (Teltonika FMB125) |
| Fichiers | SharePoint (Microsoft Graph API) |
| Exports | Maatwebsite/Excel (Excel/CSV) |
| Permissions | Spatie Laravel Permission |
| Build | Vite 6.4 |

## Modules

### Transport de basalte
- Suivi des rotations (pesées fournisseur/client, écart de poids, références)
- Validation avec verrouillage (les rotations validées sont immuables)
- Import Excel en masse
- Produits : 0/3, 3/8, 8/16

### Gestion de flotte
- Camions avec matricule, transporteur, compteur kilométrique
- Conducteurs et transporteurs
- Fournisseurs (avec coordonnées GPS optionnelles)
- Synchronisation GPS Fleeti toutes les 30 minutes (planifié)

### Télémétrie & snapshots
- `truck_telemetry_snapshots` : capture lossless de chaque sync Fleeti (GPS, vitesse, carburant, heures moteur, tension batterie, signal, charge brute JSON)
- `kilometer_trackings` : incréments de distance avec détection de reset compteur
- `fuel_trackings` : historique carburant avec position GPS et état d'allumage
- `engine_hour_trackings` : heures moteur (maintenance basée sur l'usure)
- `fuel_events` : détection automatique de ravitaillements, baisses, et vols suspectés
- Compactage mensuel des snapshots anciens (90j = 1/heure, 365j = 1/jour)

### Détection de vol
- **Vol de carburant** : baisse de niveau quand le moteur est éteint
- **Écart de poids** : différence fournisseur/client au-delà du seuil (300 kg par défaut)
- **Arrêts non autorisés** : arrêts > 20 min à des lieux inconnus pendant une mission
- **Mouvement hors horaires** : camion en mouvement sans mission active en dehors des heures de travail
- **Déviation d'itinéraire** : distance réelle >> distance attendue entre origine et destination
- Incidents unifiés dans `theft_incidents` avec statut (En attente / Examiné / Confirmé / Rejeté)
- Alertes mirrored dans `logistics_alerts` pour le tableau de bord logistique

### Cartographie
- **Carte de la flotte** (`/logistics/fleet-map`) : position temps réel de tous les camions
- **Reprise de trajet** (`/transport_tracking/{id}/replay`) : trace GPS polyline + arrêts + incidents
- **Lieux / Géofences** (`/logistics/places`) : bases, sites fournisseurs, stations-service avec rayon configurable
- Détection automatique des bases depuis les données de stationnement (commande nocturne)

### Maintenance
- Double système : rotations (12 max) ou kilométrage (configurable par type)
- Profils de maintenance par camion (général, huile, pneus, filtres) avec intervalle immuable
- Alertes automatiques quand la maintenance est due
- Checklists quotidiens conducteurs

### Rapports & exports
- Export Excel sur chaque DataTable (CSV UTF-8 BOM + séparateur `;` pour Excel français)
- Rapports dédiés : transport, flotte, maintenance, maintenance due
- Stockage documents sur SharePoint (Microsoft Graph API)

## Rôles

| Rôle | Accès |
|------|-------|
| Super Admin | Tout |
| Admin | Tout sauf gestion des rôles |
| Manager | Logistique, flotte, transport, maintenance, incidents de vol |
| Driver | Checklist quotidien, mes voyages, mon camion |

## Installation

```bash
# Cloner le projet
git clone <repo-url>
cd AMC-Logistics

# Backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed

# Frontend
npm install
npm run build        # production
npm run dev          # développement (HMR)
```

## Variables d'environnement clés

```env
# Fleeti API
FLEETI_API_URL=https://api.fleeti.co
FLEETI_API_KEY=
FLEETI_CUSTOMER_REFERENCE=

# SharePoint (Microsoft Graph)
MICROSOFT_GRAPH_TENANT_ID=
MICROSOFT_GRAPH_CLIENT_ID=
MICROSOFT_GRAPH_CLIENT_SECRET=
SHAREPOINT_SITE_ID=
SHAREPOINT_DRIVE_ID=

# Sync & détection
FLEETI_SYNC_INTERVAL_MINUTES=30
WEIGHT_GAP_THRESHOLD_KG=300
UNAUTHORIZED_STOP_MIN_DURATION_SECONDS=1200
LOGISTICS_WORK_HOURS_START=05:00
LOGISTICS_WORK_HOURS_END=21:00
LOGISTICS_WORK_DAYS=1,2,3,4,5,6
```

## Commandes planifiées

| Commande | Fréquence | Description |
|----------|-----------|-------------|
| `fleeti:sync-kilometers` | 30 min | Synchronise télémétrie Fleeti (GPS, carburant, km, heures moteur) |
| `logistics:notify-due-engine-maintenance` | 15 min | Alertes maintenance due |
| `logistics:notify-missing-daily-checklists` | 15 min | Alertes checklists manquants |
| `logistics:detect-off-hours-movement` | 1h | Détecte les camions en mouvement hors horaires |
| `places:detect-hubs` | Quotidien 02:30 | Auto-détecte les bases depuis les données de stationnement |
| `logistics:rebuild-trip-segments` | Quotidien 02:45 | Reconstruit les segments de trajet pour les transports récents |
| `telemetry:compact` | Mensuel (1er) | Compacte les snapshots anciens |

Pour activer le planificateur :

```bash
# Développement (reste actif dans le terminal)
php artisan schedule:work

# Production (cron Infomaniak — une seule entrée)
* * * * * php /chemin/vers/artisan schedule:run >> /dev/null 2>&1
```

## Structure des services

```
app/Services/
├── FleetiService.php              # Extracteurs API Fleeti (km, carburant, GPS, vitesse, etc.)
├── FleetiSyncService.php          # Orchestrateur sync : snapshot → km → fuel → engine → stops → incidents
├── TelemetrySnapshotService.php   # Écrit les snapshots + cache trucks.fleeti_last_*
├── KilometerService.php           # Gestion des incréments km + détection reset compteur
├── EngineHoursService.php         # Idem pour les heures moteur
├── FuelTrackingService.php        # Écrit les enregistrements carburant enrichis (GPS, allumage)
├── FuelEventDetectorService.php   # Détecte ravitaillements, baisses, vols suspectés
├── GeoService.php                 # Haversine, point-in-radius, nearestPlace
├── StopDetectorService.php        # Dérive les truck_stops depuis les snapshots
├── PlaceClassifierService.php     # Classe chaque arrêt contre les géofences
├── TripSegmentBuilderService.php  # Construit trip_segments pour chaque transport
├── TheftIncidentService.php       # Écrit/déduplique les incidents de vol
├── WeightGapDetector.php          # Détecte les écarts de poids suspects
├── UnauthorizedStopDetector.php   # Détecte les arrêts non autorisés en mission
├── OffHoursMovementDetector.php   # Détecte les mouvements hors horaires
├── RouteDeviationDetector.php     # Détecte les déviations d'itinéraire
├── MaintenanceStatusService.php   # Recalcule les profils de maintenance
└── SharePointStorageService.php   # Upload/download fichiers vers SharePoint
```

## Tests

```bash
php vendor/bin/phpunit                          # tous les tests
php vendor/bin/phpunit --filter=GeoServiceTest  # tests GeoService uniquement
```
