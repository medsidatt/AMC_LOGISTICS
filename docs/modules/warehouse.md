# Module: Warehouse (Planned Extension)

## Purpose

Define and document warehouse operations that should integrate with the existing shipment and fleet modules.

## Current State

- No dedicated warehouse controller/routes module identified yet.
- Warehouse behavior is partially implied through shipment stock records in `transport_tracking`.
- This file is the planning baseline for warehouse implementation.

## Proposed Scope

- Stock receiving from providers
- Stock release/dispatch to transport operations
- Inventory balance by product and location
- Loss/gain reconciliation with shipment records
- Warehouse movement history and audit logs

## Suggested Architecture Direction

- Add dedicated routes file: `routes/web/warehouse_route.php`
- Introduce `WarehouseController` and `InventoryMovementController`
- Add models for `Warehouse`, `WarehouseLocation`, `InventoryMovement`
- Keep movement links to existing `transport_trackings` references

## Integration Rules

- Shipment remains source of transport movement records
- Warehouse tracks internal stock state transitions
- Fleet module handles transport asset readiness
- Payroll module remains untouched unless permissions/users are shared

## Implementation Phases

### Phase 1: Foundations
- Warehouse master data (sites/locations)
- Product stock opening balances

### Phase 2: Operations
- Receive, transfer, dispatch workflows
- Inventory transaction ledger

### Phase 3: Controls and Analytics
- Reconciliation dashboard with shipment weights
- Exception detection and approval workflows

## Open Questions

- Single warehouse or multi-warehouse rollout?
- Product catalog source (reuse existing values or new master table)?
- Required approvals for stock adjustments?
- Required links to finance/payroll reporting?
