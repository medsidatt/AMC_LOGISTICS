# Read Model Inventory (R1.2)

> Audit of every query feeding a calculation/dashboard, the Read Models that will own them,
> and which Domain Calculators (R1.3) consume each. Read Models are **immutable business
> projections** — they join/aggregate/filter/normalize and return DTOs. They never calculate
> KPIs, compare thresholds, classify, read Operational Parameters, emit events, or expose Eloquent.

## 1. Inventory (by business question → future Read Model)

### `TransportTrackingReadModel` (loads / tickets)
| Business question | Current sites (file) | Shape | Duplication |
|---|---|---|---|
| Rotations + tonnage **per truck** for a period | `FleetKpiService:34`, `RotationAchievementService:66`, `TruckKpiService:24` | `groupBy(truck_id)` COUNT+SUM | **×3 identical** |
| Rotations + tonnage **per driver** for a period | `FleetKpiService:171`, `DriverKpiService:34` | `groupBy(driver_id)` COUNT+SUM | **×2 identical** |
| Period **totals** (trips, provider/client/gap tonnage) | `TrackingDashboardController:67-81` | COUNT + SUM(+ABS) | unique |
| **Monthly tonnage**, fiscal 22→21 grouping | `DashboardDataService:74`, `TrackingDashboardController:114` | `selectRaw(CASE DAY()>=22 …)` groupBy | **×2 identical** |
| Trips today / yesterday / this-week / this-month counts | `DashboardDataService:34/35/238/243/470` | `whereDate`/`whereMonth` COUNT | scattered (deferred) |
| Anomalies / top-risky-driver / gap-by-product / gap-by-base | `TrackingDashboardController:128-160` | `selectRaw` groupBy + ABS | report-only (deferred) |

### `FleetReadModel` (trucks / fleet state)
| Business question | Current sites (file) | Shape | Duplication |
|---|---|---|---|
| **Active trucks** (roster, all properties) | `FleetCapacityService:69`, `FleetKpiService:30`, `DashboardDataService:142` | `where(is_active)->get()` | **×7+ identical** |
| **Active + available** trucks | `FleetObjectiveService:41`, `FleetOptimizerService:33`, `PlanningWorkspaceService:201` | `+where(is_available)` | **×4 identical** |
| Active truck **count** | `DashboardDataService:32`, `PlanningWorkspaceService:200` | COUNT | **×2 identical** |
| Available **capacity tonnage** sum | `RotationAchievementService:354` | SUM(capacity_tonnage) | unique |
| Per-truck history / week actuals | `FleetCapacityService:181/213` | per-truck loops | **N+1** (deferred) |
| Maintenance-due / inspection-due lists | `DashboardDataService:90/316/421/390/513` | get→filter | **×4** (belongs to Maintenance/Inspection RM — deferred) |

*(Dispatch / Maintenance / Fuel / Inspection Read Models are deferred until their own inventories are complete, per the R1.2 brief.)*

## 2. Dependency map
```
trucks ───────────────► FleetReadModel ───────────────► CapacityCalculator, UtilizationCalculator,
                                                          RotationCalculator, ObjectiveCalculator
transport_trackings ──► TransportTrackingReadModel ────► RotationCalculator, WeightCalculator,
                                                          ProductivityCalculator, CycleCalculator,
                                                          BillingCalculator, ObjectiveCalculator
```
Read Models depend only on Eloquent models. Calculators depend on Read Models + `OperationalParameterService`. No layer is bypassed.

## 3. Ownership matrix (Read Model → consuming calculators, R1.3+)
| Read Model | Method | Consumed by (calculator) |
|---|---|---|
| TransportTrackingReadModel | `aggregateByTruck` | Rotation, Productivity, Utilization |
| TransportTrackingReadModel | `aggregateByDriver` | Productivity |
| TransportTrackingReadModel | `monthlyTonnage` | Rotation, Objective |
| TransportTrackingReadModel | `periodTotals` | Weight, Billing |
| FleetReadModel | `activeTrucks` | Capacity, Utilization, Rotation |
| FleetReadModel | `activeAvailableTrucks` | Capacity, Objective |
| FleetReadModel | `activeTruckCount` | Capacity |
| FleetReadModel | `availableCapacityTonnage` | Capacity, Objective |

## 4. Performance observations (ranked — identify only, do NOT optimize in R1.2)
1. **Active-truck list re-queried ×7+** per request across services → cacheable in `FleetReadModel` (R1.8).
2. **Per-truck history loops** (`FleetCapacityService:181/213`) → **N+1** over trucks → batch in R1.8.
3. **Fiscal-month tonnage** computed twice (`DashboardDataService` + `TrackingDashboardController`) → single Read Model query.
4. **Per-truck / per-driver aggregate** computed in 3 / 2 places → single query each.

## 5. Migration policy
Read Models are created **additively** in R1.2 (no consumer yet → zero behavioural risk). The duplicated
inline queries are **removed in R1.8**, one consumer at a time, each guarded by characterization tests
proving identical output. Only **identical** queries are merged; queries answering different business
questions stay separate.
