-- =============================================================================
-- PRODUCTION DATABASE RESET — go-live "start from scratch"
-- Target: Infomaniak prod (amc_logistics)
-- Author: prepared 2026-05-21
--
-- KEEPS:    transport_trackings, documents, users/roles/permissions, drivers,
--           trucks (master data), transporters, providers, entities, projects,
--           fleet_settings, truck_maintenance_profiles, daily_dispatches,
--           monthly_tonnage_targets, client_demand_plans, objective_history_entries
-- WIPES:    maintenance, inspections, driver theft/discipline, Fleeti telemetry,
--           derived theft-detection tables, audit log, in-app notifications,
--           and cached telemetry columns on `trucks`
--
-- BEFORE RUNNING:
--   1. Take a full DB backup via Infomaniak admin panel ("Manager → Bases de
--      données → amc_logistics → Sauvegardes"). Verify the .sql.gz file
--      downloads to your computer before you proceed.
--   2. Put the app in maintenance mode:  php artisan down  (over SSH, or via
--      a .htaccess redirect, or by uploading a maintenance.html).
--   3. Stop the Fleeti sync scheduler (so it doesn't re-write rows while
--      we're truncating): either pause the cron in Infomaniak, or upload a
--      version of the app where `schedule->command('fleeti:...')` calls
--      have been temporarily commented out.
--
-- EXECUTION:
--   Run this entire file as ONE transaction-like batch (foreign key checks
--   are disabled at the start and re-enabled at the end). In phpMyAdmin:
--   open the SQL tab, paste, click "Go". In MySQL CLI:
--      mysql -u <user> -p <db> < prod_reset_2026-05-21.sql
--
-- AFTER:
--   1. Re-enable Fleeti sync.
--   2. Take the app out of maintenance: php artisan up.
--   3. Run a sanity SELECT (last section of this file).
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ── Maintenance history ─────────────────────────────────────────────────
TRUNCATE TABLE maintenances;

-- ── Inspections (HSE + driver weekly checklists) ─────────────────────────
TRUNCATE TABLE inspection_checklist_issues;
TRUNCATE TABLE inspection_checklists;
TRUNCATE TABLE daily_checklist_issues;
TRUNCATE TABLE daily_checklists;

-- ── Driver discipline / theft incidents ──────────────────────────────────
TRUNCATE TABLE driver_discipline_records;
TRUNCATE TABLE theft_incidents;

-- ── Fleeti telemetry & derivatives ───────────────────────────────────────
TRUNCATE TABLE fleeti_daily_records;
TRUNCATE TABLE truck_telemetry_snapshots;
TRUNCATE TABLE kilometer_trackings;
TRUNCATE TABLE engine_hour_trackings;
TRUNCATE TABLE fuel_trackings;
TRUNCATE TABLE fuel_events;
TRUNCATE TABLE edk_fuel_transactions;
TRUNCATE TABLE places;
TRUNCATE TABLE trip_segments;
TRUNCATE TABLE truck_stops;
TRUNCATE TABLE logistics_alerts;

-- ── System cleanup (consistent with "from scratch") ─────────────────────
TRUNCATE TABLE audit_logs;
TRUNCATE TABLE notifications;

-- ── Reset cached telemetry on master `trucks` table ─────────────────────
-- KEEP: fleeti_asset_id, fleeti_gateway_id (permanent device IDs; not
-- telemetry data). Wipe everything derived from telemetry sync.
UPDATE trucks SET
    total_kilometers              = 0,
    fleeti_last_kilometers        = NULL,
    fleeti_last_fuel_level        = NULL,
    fleeti_last_engine_hours      = NULL,
    fleeti_last_speed_kmh         = NULL,
    fleeti_last_latitude          = NULL,
    fleeti_last_longitude         = NULL,
    fleeti_last_heading_deg       = NULL,
    fleeti_last_ignition_on       = NULL,
    fleeti_last_movement_status   = NULL,
    fleeti_last_battery_voltage   = NULL,
    fleeti_last_signal_strength   = NULL,
    fleeti_device_last_seen_at    = NULL,
    fleeti_last_synced_at         = NULL;

SET FOREIGN_KEY_CHECKS = 1;

-- ── Post-run sanity check ────────────────────────────────────────────────
-- Run these after to confirm everything is as expected. transport_trackings
-- and master-data tables should match their pre-reset row counts.
SELECT 'transport_trackings' AS tbl, COUNT(*) n FROM transport_trackings
UNION ALL SELECT 'documents',        COUNT(*) FROM documents
UNION ALL SELECT 'drivers',          COUNT(*) FROM drivers
UNION ALL SELECT 'trucks',           COUNT(*) FROM trucks
UNION ALL SELECT 'users',            COUNT(*) FROM users
UNION ALL SELECT 'providers',        COUNT(*) FROM providers
UNION ALL SELECT 'maintenances (=0)',                COUNT(*) FROM maintenances
UNION ALL SELECT 'inspection_checklists (=0)',       COUNT(*) FROM inspection_checklists
UNION ALL SELECT 'daily_checklists (=0)',            COUNT(*) FROM daily_checklists
UNION ALL SELECT 'driver_discipline_records (=0)',   COUNT(*) FROM driver_discipline_records
UNION ALL SELECT 'theft_incidents (=0)',             COUNT(*) FROM theft_incidents
UNION ALL SELECT 'fleeti_daily_records (=0)',        COUNT(*) FROM fleeti_daily_records
UNION ALL SELECT 'truck_telemetry_snapshots (=0)',   COUNT(*) FROM truck_telemetry_snapshots
UNION ALL SELECT 'kilometer_trackings (=0)',         COUNT(*) FROM kilometer_trackings
UNION ALL SELECT 'engine_hour_trackings (=0)',       COUNT(*) FROM engine_hour_trackings
UNION ALL SELECT 'fuel_trackings (=0)',              COUNT(*) FROM fuel_trackings
UNION ALL SELECT 'fuel_events (=0)',                 COUNT(*) FROM fuel_events
UNION ALL SELECT 'edk_fuel_transactions (=0)',       COUNT(*) FROM edk_fuel_transactions
UNION ALL SELECT 'places (=0)',                      COUNT(*) FROM places
UNION ALL SELECT 'trip_segments (=0)',               COUNT(*) FROM trip_segments
UNION ALL SELECT 'truck_stops (=0)',                 COUNT(*) FROM truck_stops
UNION ALL SELECT 'logistics_alerts (=0)',            COUNT(*) FROM logistics_alerts
UNION ALL SELECT 'audit_logs (=0)',                  COUNT(*) FROM audit_logs
UNION ALL SELECT 'notifications (=0)',               COUNT(*) FROM notifications;

-- Spot-check: trucks should still be 97 rows and their telemetry should be NULL
SELECT COUNT(*)                                                       AS trucks_total,
       SUM(fleeti_last_kilometers IS NULL)                            AS km_null,
       SUM(fleeti_last_synced_at  IS NULL)                            AS sync_null,
       SUM(total_kilometers = 0)                                      AS km_zero
FROM trucks;
