export interface MaintenanceProfile {
    type: string;
    interval_km: number;
    next_km?: number;
    remaining?: number;
    status?: string;
}

export interface InspectionIssue {
    id: number;
    category: string;
    severity: string;
    issue_notes: string | null;
    inspection_date: string | null;
    parts_cost?: string | null;
    labor_cost?: string | null;
    total_cost?: string | null;
    devis_url?: string | null;
    devis_name?: string | null;
}

export interface BoardTruck {
    id: number;
    matricule: string;
    total_kilometers: number;
    maintenance_type: string;
    profiles: MaintenanceProfile[];
    overall_status: string;
    open_issues: number;
    open_inspection_issues: number;
    inspection_issues: InspectionIssue[];
}

export interface MaintenanceLineItem {
    designation: string;
    product_id?: number | null;
    reference: string | null;
    category: string;
    unit: string;
    quantity: number;
    unit_price: number;
    line_total: number;
}

export type MaintenanceStatus = 'pending' | 'assigned' | 'completed' | 'approved';

export interface MaintenanceRecord {
    id: number;
    truck: string;
    maintenance_type: string;
    maintenance_date: string;
    kilometers_at_maintenance: number;
    trigger_km: number | null;
    interval_km: number | null;
    notes: string | null;
    oil_type?: string | null;
    oil_type_label?: string | null;
    oil_change_km?: number | null;
    next_oil_change_km?: number | null;
    oil_quantity_liters?: number | null;
    gearbox_status?: string | null;
    differential_status?: string | null;
    hydraulic_status?: string | null;
    greasing_status?: string | null;
    brake_status?: string | null;
    coolant_status?: string | null;
    battery_status?: string | null;
    filter_oil_changed?: boolean;
    filter_hydraulic_changed?: boolean;
    filter_air_changed?: boolean;
    filter_fuel_changed?: boolean;
    dashboard_photo_url?: string | null;
    attachment_url?: string | null;
    attachment_filename?: string | null;
    status: MaintenanceStatus;
    signed_by: string | null;
    approved_at: string | null;
    truck_interval_km?: number | null;
    items?: MaintenanceLineItem[];
    control_checks?: Record<string, string>;
}

export interface RuleProfile {
    id: number;
    truck_id: number;
    truck: string | null;
    maintenance_type: string;
    interval_km: number;
    warning_threshold_km: number | null;
    status: string;
    is_active: boolean;
    deactivated_at: string | null;
    created_at: string | null;
}

export interface MaintenanceTypeOpt { value: string; label: string }

export interface MaintenanceRefs {
    oilTypes: Record<string, string>;
    oilIntervals: Record<string, number>;
    componentStatuses: Record<string, string>;
    itemCategories: Record<string, string>;
    itemUnits: Record<string, string>;
    controlChecks: Record<string, string>;
}
