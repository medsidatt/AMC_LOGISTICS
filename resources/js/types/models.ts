export interface Truck {
    id: number;
    matricule: string;
    is_active: boolean;
    maintenance_type: 'rotation' | 'kilometer';
    total_kilometers: number;
    transporter_id: number | null;
    fleeti_id: string | null;
    transporter?: Transporter;
    deleted_at: string | null;
}

export interface Driver {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    user_id: number | null;
    deleted_at: string | null;
}

export interface TransportTracking {
    id: number;
    reference: string;
    truck_id: number | null;
    driver_id: number | null;
    provider_id: number | null;
    transporter_id: number | null;
    product: string | null;
    base: string | null;
    provider_net_weight: number | null;
    client_net_weight: number | null;
    gap: number | null;
    provider_date: string | null;
    client_date: string | null;
    provider_file: string | null;
    client_file: string | null;
    truck?: Truck;
    driver?: Driver;
    provider?: Provider;
    deleted_at: string | null;
}

export interface Provider {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    deleted_at: string | null;
}

export interface Transporter {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    deleted_at: string | null;
}

export interface Maintenance {
    id: number;
    truck_id: number;
    maintenance_date: string;
    maintenance_type: string;
    kilometers_at_maintenance: number | null;
    notes: string | null;
    truck?: Truck;
}

export interface DailyChecklist {
    id: number;
    truck_id: number;
    driver_id: number;
    checklist_date: string;
    truck?: Truck;
    driver?: Driver;
    issues?: DailyChecklistIssue[];
}

export interface DailyChecklistIssue {
    id: number;
    daily_checklist_id: number;
    description: string;
    resolved_at: string | null;
    resolution_notes: string | null;
}

export interface LogisticsAlert {
    id: number;
    type: string;
    message: string;
    truck_id: number | null;
    read_at: string | null;
    resolved_at: string | null;
    truck?: Truck;
    created_at: string;
}

// Dashboard-specific types

export interface KpiItem {
    label: string;
    value: number | string;
    unit?: string;
    change?: number;
    changeLabel?: string;
    color?: 'primary' | 'success' | 'danger' | 'warning' | 'info';
    icon?: string;
}

export interface ChartSeries {
    name: string;
    data: number[];
}

export interface MonthlyData {
    months: string[];
    series: ChartSeries[];
}

export interface FilterState {
    start_date?: string;
    end_date?: string;
    driver_id?: number | string;
    truck_id?: number | string;
    provider_id?: number | string;
    transporter_id?: number | string;
    product?: string;
    base?: string;
}

export interface Insight {
    type: 'info' | 'warning' | 'success' | 'danger';
    icon: string;
    message: string;
    metric?: string;
}

export interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}
