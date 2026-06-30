<?php

namespace App\Enums;

/**
 * The single authoritative list of operational parameter keys.
 * Calculators and the seeder reference these cases — never raw string literals.
 * Adding a parameter = adding a case here + a seeder row. See ADR-008.
 */
enum OperationalParameterKey: string
{
    case DEFAULT_CAPACITY = 'default_capacity_tonnage';
    case CAPACITY_BUFFER_RATIO = 'capacity_buffer_ratio';
    case TARGET_ROTATIONS = 'target_rotations_per_week';
    case MONTHLY_TARGET_TONNAGE = 'monthly_target_tonnage';
    case CYCLE_TIME_HOURS = 'cycle_time_hours';
    case WEIGHT_OPERATIONAL_THRESHOLD = 'weight_operational_threshold_t';
    case WEIGHT_FRAUD_THRESHOLD = 'weight_fraud_threshold_kg';
    case WEIGHT_SENSOR_THRESHOLD = 'weight_sensor_threshold_kg';
    case WEIGHT_ANOMALY_RATIO = 'weight_anomaly_ratio';
    case PRICE_PER_LITRE = 'price_per_litre';
    case FISCAL_MONTH_START_DAY = 'fiscal_month_start_day';
    case INSPECTION_SLA_DAYS = 'inspection_sla_days';
    case MAX_ROTATIONS_BEFORE_MAINTENANCE = 'max_rotations_before_maintenance';
    case MAX_KM_BEFORE_MAINTENANCE = 'max_km_before_maintenance';
    case WARNING_THRESHOLD_KM = 'warning_threshold_km';
    case MAINTENANCE_WARNING_RATIO = 'maintenance_warning_ratio';
}
