# Migration Strategy: Payroll to Logistics Extension

## Principle

This is an extension project, not a rewrite.

## Strategy

1. Preserve current payroll flows and schema compatibility.
2. Introduce logistics modules behind separate services/routes/views where possible.
3. Reuse shared components only when behavior remains backward compatible.
4. Add feature flags or role-based access to isolate logistics rollout.
5. Track each change with regression checks for payroll.

## Workstream Breakdown

### 1) Domain Mapping

- Identify payroll entities that can be reused.
- Define new logistics entities and relationships.
- Document shared entities to avoid duplication.

### 2) Application Layer

- Add logistics controllers/services/use-cases.
- Keep payroll controllers untouched unless required for shared dependencies.
- Avoid coupling new logistics logic directly into payroll-heavy classes.

### 3) UI Layer

- Add logistics navigation and pages.
- Keep payroll menus and user flows unchanged.
- Use common layout components where safe.

### 4) Data Layer

- Add new logistics tables and migrations.
- Avoid destructive migration changes to payroll tables.
- Add indexes needed for route/delivery queries.

### 5) QA and Validation

- Build a logistics test checklist per module.
- Run payroll smoke tests after logistics changes.
- Log all regressions and resolutions in `work-log.md`.

## Definition of Done (Per Feature)

- Business behavior implemented.
- No payroll regression found in smoke tests.
- Documentation updated in this folder.
