# Module: Shipment

## Purpose

Manage transport stock/shipments from provider to client with weight comparison, document attachments, and anomaly monitoring.

## Current Code Mapping

- Routes file: `routes/web/transport_basalt_route.php`
- Prefix and names: `transport_tracking/*`, route names `transport_tracking.*`
- Main controller: `App\Http\Controllers\TransportTrackingController`
- Main model: `App\Models\TransportTracking`
- Related models: `Truck`, `Driver`, `Provider`, `Transporter`, `Document`
- Main views: `resources/views/pages/transport_trackings/*`

## Existing Capabilities

- Create, edit, list, show, and delete transport tracking records
- Import transport tracking data from Excel/CSV
- Upload and classify supporting files (provider/client/commune)
- Preview and merge PDF files linked to a tracking record
- Export tracking data and export missing/incomplete records
- Dashboard with KPI metrics and date/entity filters
- AI-assisted analysis for tracking discrepancies

## Key Business Fields

- Movement identity: reference, truck, driver, transporter, provider
- Product context: product type and base (`mr`, `sn`, `none`)
- Weight flow: provider/client gross, tare, net weights
- Dates: provider date, client date, commune date
- Gap logic: compare provider and client net weights

## Risks and Constraints

- Prevent duplicate records for same truck/date pattern
- Keep file storage and DB metadata consistent on update/delete
- Ensure exports keep existing payroll exports unaffected
- Keep route names stable to avoid UI and JS modal regressions

## Upgrade Backlog (Suggested)

- Add explicit shipment status lifecycle (planned/in-transit/delivered/closed)
- Add stronger duplicate detection with configurable keys
- Introduce configurable anomaly thresholds per product/base
- Add audit trail for record edits and file changes
