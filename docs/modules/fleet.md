# Module: Fleet

## Purpose

Manage logistics assets and operations related to trucks, transporters, drivers, and maintenance planning.

## Current Code Mapping

- Routes file: `routes/web/transport_basalt_route.php` and `routes/web/truck_route.php`
- Prefix and names: `trucks/*`, route names `trucks.*`
- Main controller: `App\Http\Controllers\TruckController`
- Related controllers: `DriverController`, `TransporterController`, `ProviderController`
- Main models: `Truck`, `Maintenance`, `Driver`, `Transporter`
- Main views: `resources/views/pages/trucks/*`, `resources/views/pages/drivers/*`, `resources/views/pages/transporters/*`

## Existing Capabilities

- Full CRUD for trucks
- Active/inactive truck status management
- Maintenance management per truck
- Support for maintenance mode by rotations or kilometers
- Bulk maintenance operations and bulk maintenance settings updates
- Maintenance due reporting and exports (Excel/PDF)
- Truck detail view with recent transport history

## Operational Logic

- Maintenance status is calculated dynamically from truck data
- Separate counters and thresholds by maintenance type
- Maintenance profile interval can be updated globally or per truck
- Truck usage ties directly to shipment records (`transport_trackings`)

## Risks and Constraints

- Maintenance calculations affect operational continuity
- Changes to truck IDs/relations can impact shipment records
- Driver/transporter consistency is required for clean dashboards
- Keep payroll logic isolated when touching shared layout/components

## Upgrade Backlog (Suggested)

- Add fleet availability planning calendar
- Add preventive maintenance reminders/notifications
- Add vehicle compliance documents with expiry tracking
- Add role-based approvals for fleet-critical operations
