<?php

namespace App\Domain\Operations\Events;

/** Stable identity of every business event type. Never renumbered/reused. */
enum EventId: string
{
    case TRUCK_UNAVAILABLE = 'truck_unavailable';
    case MAINTENANCE_OVERDUE = 'maintenance_overdue';
    case MAINTENANCE_WARNING = 'maintenance_warning';
    case WEIGHT_ANOMALY_DETECTED = 'weight_anomaly_detected';
    case FUEL_CONSUMPTION_ABNORMAL = 'fuel_consumption_abnormal';
    case INSPECTION_EXPIRED = 'inspection_expired';
    case INSPECTION_DUE = 'inspection_due';
    case DISPATCH_DELAYED = 'dispatch_delayed';
    case DISPATCH_COMPLETED = 'dispatch_completed';
    case MISSING_TRANSPORT_TICKET = 'missing_transport_ticket';
    case GPS_LOAD_WITHOUT_TICKET = 'gps_load_without_ticket';
    case BILLING_BLOCKED = 'billing_blocked';
    case OBJECTIVE_BEHIND_SCHEDULE = 'objective_behind_schedule';
    case OBJECTIVE_REACHED = 'objective_reached';
    case CAPACITY_REDUCED = 'capacity_reduced';
    case DRIVER_DISCIPLINE_LOW = 'driver_discipline_low';
}
