<?php

return [
    'warning_threshold_km' => (float) env('MAINTENANCE_WARNING_THRESHOLD_KM', 500),
    'max_single_trip_distance_km' => (float) env('MAINTENANCE_MAX_TRIP_DISTANCE_KM', 2000),
    'odometer_reset_threshold_km' => (float) env('ODOMETER_RESET_THRESHOLD_KM', 50000),
    'engine_hours_reset_threshold' => (float) env('ENGINE_HOURS_RESET_THRESHOLD', 1000),
    'fleeti_sync_interval_minutes' => (int) env('FLEETI_SYNC_INTERVAL_MINUTES', 30),

    // Fuel event detection thresholds
    'fuel_refill_threshold_litres' => (float) env('FUEL_REFILL_THRESHOLD_LITRES', 30),
    'fuel_drop_threshold_litres' => (float) env('FUEL_DROP_THRESHOLD_LITRES', 15),

    // Telemetry snapshot retention
    'telemetry_snapshot_retention_days' => (int) env('TELEMETRY_SNAPSHOT_RETENTION_DAYS', 90),
    'telemetry_compact_hourly_after_days' => (int) env('TELEMETRY_COMPACT_HOURLY_AFTER_DAYS', 90),
    'telemetry_compact_daily_after_days' => (int) env('TELEMETRY_COMPACT_DAILY_AFTER_DAYS', 365),

    // ------------------------------------------------------------------
    // Theft-detection layer (Phase A)
    // ------------------------------------------------------------------

    // Work hours outside of which any movement is flagged as suspicious
    'work_hours' => [
        'start' => env('LOGISTICS_WORK_HOURS_START', '05:00'),
        'end'   => env('LOGISTICS_WORK_HOURS_END',   '21:00'),
        // 1=Mon … 7=Sun (Carbon::dayOfWeekIso)
        'days'  => array_filter(
            array_map('intval', explode(',', env('LOGISTICS_WORK_DAYS', '1,2,3,4,5,6')))
        ),
    ],

    // Minimum duration for a parked window to be recorded as a truck_stop
    'stops_min_duration_seconds' => (int) env('STOPS_MIN_DURATION_SECONDS', 300),

    // Stop longer than this (at an unknown place, during a loaded trip) is
    // escalated to an unauthorized_stop theft incident
    'unauthorized_stop_min_duration_seconds' => (int) env('UNAUTHORIZED_STOP_MIN_DURATION_SECONDS', 1200),

    // Minimum weight gap (kg) that triggers a weight_gap incident.
    // gap = client_net_weight - provider_net_weight; negative means cargo lost en route.
    'weight_gap_threshold_kg' => (float) env('WEIGHT_GAP_THRESHOLD_KG', 300),

    // Hub detection (nightly places:detect-hubs command)
    'hub_detection_min_parked_hours' => (int) env('HUB_DETECTION_MIN_PARKED_HOURS', 2),
    'hub_detection_cluster_radius_m' => (int) env('HUB_DETECTION_CLUSTER_RADIUS_M', 250),

    // Factor applied to straight-line origin→destination distance before flagging
    // a route deviation (actual_km > straight_line_km * factor)
    'route_deviation_factor' => (float) env('ROUTE_DEVIATION_FACTOR', 1.6),

    // Speed threshold (km/h) above which a truck is considered "moving" for the
    // off-hours detector
    'off_hours_min_speed_kmh' => (float) env('OFF_HOURS_MIN_SPEED_KMH', 5),

    // Default geofence radius (metres) applied to auto-detected places
    'place_default_radius_m' => (int) env('PLACE_DEFAULT_RADIUS_M', 300),
    'types' => [
        'general' => [
            'label' => 'General',
            'default_interval_km' => 10000,
        ],
        'oil' => [
            'label' => 'Oil',
            'default_interval_km' => 10000,
        ],
        'tires' => [
            'label' => 'Tires',
            'default_interval_km' => 20000,
        ],
        'filters' => [
            'label' => 'Filters',
            'default_interval_km' => 15000,
        ],
    ],
];
