# Logistics Documentation

This folder contains all project documentation related to the migration from a payroll-focused system to a logistics-focused system.

The goal is to **upgrade and extend** the current application for logistics use cases without removing existing payroll logic.

## Files

- `logistics-overview.md`: Vision, scope, and key logistics modules.
- `migration-strategy.md`: How to keep payroll logic stable while adding logistics features.
- `work-log.md`: Day-to-day tracking of implementation progress.
- `modules/README.md`: Entry point for module-by-module logistics docs.

## Documentation Rules

1. Do not document any task as "remove payroll".
2. Every logistics change must include impact notes on existing payroll behavior.
3. Update `work-log.md` after each meaningful feature or bug fix.
4. Keep this folder as the single source of truth for migration decisions.
