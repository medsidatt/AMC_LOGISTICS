# Formules de scoring — Camions & Chauffeurs

Ce document décrit **toutes les formules de calcul de score** utilisées dans l'application AMC Logistics. Il existe trois services distincts, qui répondent à trois questions différentes — il est important de ne pas les confondre.

| Service | Question répondue | Type de score | Utilisé sur |
|---|---|---|---|
| `TruckKpiService` | Quels sont les indicateurs bruts d'un camion sur une période ? | KPI bruts, **pas de score global** | Fiche camion (`/trucks/{id}`) |
| `DriverKpiService` | Le chauffeur tient-il sa cible ? | **Score absolu** sur cible | Fiche chauffeur (`/drivers/{id}`) |
| `FleetKpiService` | Qui est le meilleur de la flotte sur cette période ? | **Score relatif** entre pairs | Widgets `Top Camions` / `Top Chauffeurs` du dashboard |

---

## 1. `TruckKpiService` — KPI bruts d'un camion

Fichier : [`app/Services/TruckKpiService.php`](../app/Services/TruckKpiService.php)

Ce service ne produit **pas de score** : il retourne uniquement des KPI bruts pour la fiche d'un camion sur une période [from, to].

### Indicateurs calculés

| KPI | Formule | Source |
|---|---|---|
| `rotations.count` | Nombre de rotations sur la période | `transport_trackings` filtré sur `truck_id` et `client_date` ∈ [from, to] |
| `rotations.tonnage_delivered` | `Σ client_net_weight` | idem |
| `rotations.tonnage_provider` | `Σ provider_net_weight` | idem |
| `weight_gap.sum` | `Σ gap` (différence poids fournisseur − client) | idem |
| `weight_gap.violations` | Nb rotations où `|gap| > weight_gap_threshold` (0.5 t par défaut) | idem |
| `cycle.avg_days` | Moyenne des écarts en jours entre `client_date` d'une rotation et `provider_date` de la suivante | trié par date |
| `load_rate.rate` | `tonnage_delivered / (capacity × rotationsCount)` | `capacity` = `truck.capacity_tonnage` (défaut 25) |
| `fuel_per_rotation` | `Σ FleetiDailyRecord.consumed / rotationsCount` | `fleeti_daily_records` |
| `fuel_yield.litres_per_tonne` | `Σ consumed / tonnage_delivered` | idem |
| `fuel_anomalies.count` | Nb événements `drop` ou `theft_suspected` | `fuel_events` |
| `fuel_anomalies.litres` | `Σ |litres_delta|` sur ces événements | idem |
| `maintenance.remaining_km` | `max(0, interval_km − km_since_maintenance)` | `trucks.km_*` |
| `maintenance.level` | `red` si remaining ≤ 0, `orange` si ≤ 10 % de l'intervalle, sinon `green` | dérivé |

> Pas de pondération, pas de normalisation. C'est l'écran de fiche qui affiche ces KPI tels quels.

---

## 2. `DriverKpiService` — Score absolu d'un chauffeur

Fichier : [`app/Services/DriverKpiService.php`](../app/Services/DriverKpiService.php)

Ce service produit un **score global 0–100** pour un chauffeur sur une période. Chaque composant est noté **contre une cible absolue** (pas contre les autres chauffeurs), puis les composants sont **pondérés à 20 % chacun**.

### Pondérations

```
WEIGHT_ROTATIONS  = 0.20
WEIGHT_CYCLE      = 0.20
WEIGHT_FUEL_GAP   = 0.20
WEIGHT_WEIGHT_GAP = 0.20
WEIGHT_DISCIPLINE = 0.20
```

### Composants

#### 2.1 Rotations (20 %)

```
plannedTonnage     = Σ MonthlyTonnageTarget sur [from, to]
avgCapacity        = moyenne(trucks.capacity_tonnage où is_active) ou 25 par défaut
activeDrivers      = count(drivers où is_active)
plannedRotations   = (plannedTonnage / avgCapacity) / activeDrivers

rotationsScore     = min(100, done / plannedRotations × 100)   si plannedRotations > 0
                   = 0                                          sinon
```

`done` = nombre de rotations livrées par ce chauffeur sur la période.

#### 2.2 Cycle (20 %)

```
avgCycleDays = moyenne( provider_date[N+1] − client_date[N] )   en jours
cycleScore   = max(0, 100 − avgCycleDays × 20)
             = 0   si moins de 2 rotations dans la période
```

Pénalité linéaire : **chaque jour entre deux rotations coûte 20 points**. Un cycle moyen ≥ 5 jours donne 0.

#### 2.3 Fuel gap (20 %)

```
anomaliesCount = count(FuelEvent où truck_id ∈ trucksDriven
                                   ET event_type ∈ [drop, theft_suspected]
                                   ET detected_at ∈ [from, to])
fuelGapScore   = max(0, 100 − anomaliesCount × 20)
```

**Chaque anomalie carburant détectée sur un camion conduit par ce chauffeur coûte 20 points.** 5 anomalies ou plus → 0.

#### 2.4 Weight gap (20 %)

```
gapViolations    = count(rotations où |gap| > weight_gap_threshold)
weightGapScore   = max(0, 100 − (gapViolations / done) × 100)   si done > 0
                 = 100                                           sinon
```

Le seuil `weight_gap_threshold` est dans `FleetSetting` (défaut **0.5 tonne**).

#### 2.5 Discipline (20 %) — composite

Sous-composants (résultats normalisés 0–1, puis pondérés) :

| Sous-composant | Poids | Formule |
|---|---|---|
| **Manuel** | 40 % | Mapping linéaire : `−10 pts → 0`, `0 pts → 0.5`, `+10 pts → 1`, clampé. Source : `DriverDisciplineRecord.points` |
| **Checklist à temps** | 20 % | `onTimeCount / expectedWeeks`. À temps = `created_at ≤ fin de la semaine (dimanche 23 h 59)` |
| **Issues critiques** | 20 % | `1 − min(1, flaggedIssues / 10)`. Au-delà de 10 issues flaggées → 0 |
| **Écarts de poids** | 20 % | `1 − min(1, gapViolations / rotationsCount)` |

```
manualN          = clamp01((manualPoints + 10) / 20)
checklistRate    = onTimeCount / expectedWeeks
issuesN          = 1 − min(1, flaggedIssues / 10)
gapsN            = 1 − min(1, gapViolations / rotationsCount)

disciplineScore  = (manualN × 0.4 + checklistRate × 0.2 + issuesN × 0.2 + gapsN × 0.2) × 100
```

#### Score global

```
globalScore = rotationsScore  × 0.20
            + cycleScore      × 0.20
            + fuelGapScore    × 0.20
            + weightGapScore  × 0.20
            + disciplineScore × 0.20
```

### Propriétés

- **Stable** : un même chauffeur ayant les mêmes performances reçoit le même score à six mois d'intervalle (ce sont les cibles qui bougent, pas les pairs).
- **Comparable dans le temps** : un chauffeur peut suivre sa propre progression.
- **N'identifie pas le meilleur** : si tout le monde est sous la cible, tout le monde a un score faible.

---

## 3. `FleetKpiService` — Score relatif (leaderboard)

Fichier : [`app/Services/FleetKpiService.php`](../app/Services/FleetKpiService.php)

Ce service produit les classements **Top 5 Camions** et **Top 5 Chauffeurs** affichés sur le dashboard. Chaque métrique est **normalisée par rapport au meilleur de la période** (`val / max` pour "plus = mieux", inversée pour la consommation), puis les composants normalisés sont moyennés à parts égales.

> Conséquence : le **#1 a toujours un score élevé**, même si en valeur absolue ses performances sont médiocres. C'est un score de **classement**, pas d'**atteinte d'objectif**.

### 3.1 `topTrucks` — Top 5 Camions

4 composants, **chacun à 25 %** :

| Composant | Normalisation |
|---|---|
| `rotations` | `rotations / max(rotations)` |
| `tonnage` | `tonnage / max(tonnage)` |
| `load_rate` | `load_rate / max(load_rate)` où `load_rate = tonnage / (capacity × rotations)` |
| `fuel_yield` | inversé : `1 − (yield − minYield) / (maxYield − minYield)`. Si `fuel_yield = null` → 0. Si tous égaux et > 0 → 1 |

```
score = (rotN + tonN + loadN + yieldN) / 4 × 100
```

Le résultat est trié décroissant ; les 5 premiers sont retournés.

### 3.2 `topDrivers` — Top 5 Chauffeurs

5 composants, **chacun à 20 %** :

| Composant | Normalisation |
|---|---|
| `rotations` | `rotations / max(rotations)` |
| `tonnage` | `tonnage / max(tonnage)` |
| `avg_load_rate` | `avg_load_rate / max(avg_load_rate)`. Calcul par chauffeur : `moyenne( client_net_weight / truck.capacity_tonnage )` sur ses rotations |
| `fuel_yield` | inversé comme pour les camions |
| `discipline` | composite (voir ci-dessous) — calcul intégré au service |

**Discipline (réutilise la recette de `DriverKpiService` mais avec normalisation par rapport au pool) :**

```
manualN       = (points − min(points)) / (max(points) − min(points))   si max > min
              = 0.5                                                    sinon
issuesN       = 1 − (flaggedIssues / max(flaggedIssues))               si max > 0
              = 1                                                      sinon
gapsN         = 1 − min(1, gapRatio)
checklistRate = onTimeCount / expectedWeeks   (idem DriverKpiService)

disciplineN   = manualN × 0.4 + checklistRate × 0.2 + issuesN × 0.2 + gapsN × 0.2
```

```
score = (rotN + tonN + loadN + yieldN + disciplineN) / 5 × 100
```

### Propriétés

- **Relatif au pool** : changer de période change tous les scores.
- **Identifie le meilleur** : il y a toujours un #1 à score élevé.
- **Pas comparable dans le temps** : un chauffeur peut voir son score baisser sans avoir changé, simplement parce qu'un autre chauffeur s'est amélioré.

---

## 4. Comparaison des deux scores chauffeur

Un même chauffeur peut avoir deux scores **différents** sur la même période :

| | `DriverKpiService` (fiche) | `FleetKpiService.topDrivers` (leaderboard) |
|---|---|---|
| Référence | Cibles absolues (`MonthlyTonnageTarget`, `weight_gap_threshold`, etc.) | Les autres chauffeurs sur la même période |
| Score 100 | Possible : atteindre toutes les cibles | Seulement pour le meilleur de la période |
| Score 0 | Possible : ne rien faire | Très rare (il faut être pire que tous sur tous les axes) |
| Cycle inclus ? | **Oui (20 %)** | Non |
| Fuel anomalies inclus ? | **Oui (20 %, score dédié)** | Indirectement via `fuel_yield` |
| Pondération | Fixe (5 × 20 %) | Égale (4 ou 5 composants) |

> **Décision actuelle** : les deux coexistent. Le score absolu sert à mesurer l'atteinte des objectifs ; le score relatif sert à classer pour la motivation / prime mensuelle. Si on souhaite un score unique, il faut choisir l'un des deux comme source de vérité (recommandation : `DriverKpiService` pour la stabilité dans le temps).

---

## 5. Sources de données utilisées

| Donnée | Table / Modèle | Utilisée par |
|---|---|---|
| Rotations | `transport_trackings` | TruckKpi, DriverKpi, FleetKpi |
| Tonnage cible mensuel | `monthly_tonnage_targets` (+ défaut dans `FleetSetting`) | DriverKpi (planned), FleetKpi (production_target) |
| Seuil écart poids | `FleetSetting.weight_gap_threshold` (défaut 0.5) | TruckKpi, DriverKpi, FleetKpi |
| Capacité camion | `trucks.capacity_tonnage` (défaut 25 dans le code, 45 dans `FleetSetting.default_capacity_tonnage`) | Tous |
| Consommation jour | `fleeti_daily_records` | TruckKpi, DriverKpi, FleetKpi |
| Anomalies carburant | `fuel_events` (types `drop`, `theft_suspected`) | TruckKpi, DriverKpi |
| Discipline manuelle | `driver_discipline_records.points` | DriverKpi, FleetKpi |
| Checklist hebdo | `daily_checklists` (`week_start_date`, `created_at`) | DriverKpi, FleetKpi |
| Issues flaggées | `daily_checklist_issues` (où `flagged = true`) | DriverKpi, FleetKpi |
| Chauffeurs actifs | `drivers.is_active` | DriverKpi (planned), FleetKpi |
| Camions actifs / dispos | `trucks.is_active`, `trucks.is_available` | FleetKpi |

---

## 6. Paramètres ajustables

Tous les paramètres ci-dessous sont éditables dans `/settings/fleet` ou par migration :

| Paramètre | Défaut | Effet |
|---|---|---|
| `weight_gap_threshold` | 0.5 t | Seuil au-delà duquel un écart fournisseur/client est une violation |
| `default_capacity_tonnage` | 45 t | Capacité utilisée si la fiche camion ne renseigne pas `capacity_tonnage` |
| `monthly_target_tonnage` | 2 000 t | Cible mensuelle par défaut (utilisée si `MonthlyTonnageTarget` n'a pas de ligne pour le mois) |
| `target_rotations_per_week` | 3 | Cible de rotations/semaine (utilisé pour la capacité, pas directement dans le scoring) |
| Pénalité par jour de cycle | 20 pts | Code en dur dans `DriverKpiService::compute()` |
| Pénalité par anomalie carburant | 20 pts | Code en dur dans `DriverKpiService::compute()` |
| Plafond "10 issues = 0" | 10 | Code en dur dans la discipline |
| Map discipline manuelle | −10 → 0, +10 → 100 | Code en dur dans la discipline |

> **Si vous voulez rendre une pénalité configurable** (ex. la pénalité par jour de cycle), il faut l'ajouter à `FleetSetting` et auditer ce changement via `ObjectiveHistoryService` (voir [`docs/work-log.md`](work-log.md)).

---

## 7. Exemples

### Exemple A — Chauffeur stable (DriverKpiService)

Période : mois en cours, cible mensuelle 2 000 t, 5 camions actifs (cap. moyenne 25 t), 4 chauffeurs actifs.

```
plannedRotations = (2000 / 25) / 4 = 20 rotations
done             = 18
rotationsScore   = min(100, 18 / 20 × 100) = 90
```

Si en plus : `avgCycleDays = 2`, `fuel anomalies = 0`, `gap violations = 1/18`, discipline = 70 :

```
cycleScore       = 100 − 2 × 20 = 60
fuelGapScore     = 100
weightGapScore   = 100 − (1/18) × 100 ≈ 94.4
disciplineScore  = 70

globalScore      = 90 × 0.2 + 60 × 0.2 + 100 × 0.2 + 94.4 × 0.2 + 70 × 0.2
                 = 82.88
```

### Exemple B — Même chauffeur dans le leaderboard (FleetKpiService)

Si dans le même mois les meilleurs sont :
- max rotations = 22, max tonnage = 540 t, max load_rate = 0.97, min fuel_yield = 0.32 L/t

Et notre chauffeur : rotations 18, tonnage 440 t, avg_load_rate 0.85, fuel_yield 0.41, discipline 70 :

```
rotN          = 18 / 22 = 0.818
tonN          = 440 / 540 = 0.815
loadN         = 0.85 / 0.97 = 0.876
yieldN        = 1 − (0.41 − 0.32) / (max − 0.32)   (dépend de max)
disciplineN   = ... normalisé contre min/max du pool

score         = (rotN + tonN + loadN + yieldN + disciplineN) / 5 × 100
```

Le score relatif peut être **plus ou moins haut** que le score absolu — sans corrélation directe.

---

## 8. Pourquoi ne pas unifier ?

Question légitime. Réponses possibles :

1. **Conserver les deux** : utile pour des publics différents (RH / management).
2. **Tout sur DriverKpiService** : remplace le `topDrivers` du dashboard par un `orderByDesc(globalScore)`. Avantage : un seul score, stable dans le temps.
3. **Tout sur FleetKpiService** : remplace la fiche chauffeur. Avantage : le score reflète toujours la position dans la flotte. Inconvénient : un chauffeur ne peut pas suivre sa progression individuelle.

Décision à prendre avec Med Sidatt — n'est pas tranchée à ce jour.
