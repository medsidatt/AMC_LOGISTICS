<?php

return [
    'truck-list',
    'truck-create',
    'truck-edit',
    'truck-delete',

    'driver-list',
    'driver-create',
    'driver-edit',
    'driver-delete',

    'transport-tracking-list',
    'transport-tracking-create',
    'transport-tracking-edit',
    'transport-tracking-delete',

    'provider-list',
    'provider-create',
    'provider-edit',
    'provider-delete',

    'transporter-list',
    'transporter-create',
    'transporter-edit',
    'transporter-delete',

    'maintenance-list',
    'maintenance-create',
    'maintenance-edit',
    'maintenance-delete',
    'maintenance-assign',
    'maintenance-approve',
    'maintenance-rule-create',
    'maintenance-rule-deactivate',
    'rotation-validate',

    'inspection-list',
    'inspection-create',
    'inspection-edit',
    'inspection-delete',

    'logistics-dashboard',

    'fleet-optimization-view',
    'fleet-optimization-run',

    'client-demand-list',
    'client-demand-create',
    'client-demand-edit',
    'client-demand-delete',

    'truck-rest-window-list',
    'truck-rest-window-edit',

    'fleet-roster-plan',

    'daily-dispatch-list',
    'daily-dispatch-edit',

    'objective-history-list',

    'report-view',

    // Previously gated by hardcoded role checks — now real, assignable permissions.
    'fleet-settings-edit',
    'fuel-import',
    'fleet-map-view',
    'driver-discipline-view',
    'driver-discipline-manage',
];
