<?php

return [
    'warning_threshold_km' => (float) env('MAINTENANCE_WARNING_THRESHOLD_KM', 500),
    'odometer_reset_threshold_km' => (float) env('ODOMETER_RESET_THRESHOLD_KM', 50000),
    'fleeti_sync_interval_minutes' => (int) env('FLEETI_SYNC_INTERVAL_MINUTES', 30),
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
