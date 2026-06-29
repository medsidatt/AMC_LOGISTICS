export interface InspectionRow {
    id: number;
    inspection_date: string | null;
    truck: { id: number; matricule: string } | null;
    inspector: string | null;
    category: string;
    status: string;
    issues_count: number;
    validator: string | null;
    validated_at: string | null;
    vehicle_photo_url: string | null;
}

export interface InspectionIssue {
    id: number;
    category: string;
    severity: string;
    flagged: boolean;
    issue_notes: string | null;
    resolution_notes: string | null;
    resolved_at: string | null;
}

export interface InspectionSection { label: string; fields: Record<string, string> }

export interface InspectionOptions {
    categories: Record<string, string>;
    conditions: Record<string, string>;
    fields: string[];
    sections: Record<string, InspectionSection>;
}

export interface InspectionDetail {
    id: number;
    truck: { id: number; matricule: string } | null;
    driver_id: number | null;
    driver: { id: number; name: string } | null;
    project_id: number | null;
    project: { id: number; name: string; code?: string | null } | null;
    activity: string | null;
    client_name: string | null;
    inspector: string | null;
    inspection_date: string | null;
    category: string;
    status: string;
    findings_summary: string | null;
    recommendations: string | null;
    field_remarks: Record<string, string>;
    validator: string | null;
    validated_at: string | null;
    validation_notes: string | null;
    attachment_url: string | null;
    attachment_filename: string | null;
    vehicle_photo_url: string | null;
    vehicle_photo_filename: string | null;
    issues: InspectionIssue[];
    [field: string]: any;
}

export interface InspectionFormRefs {
    trucks: { id: number; matricule: string }[];
    drivers: { id: number; name: string }[];
    projects: { id: number; name: string; code?: string | null }[];
    defaultProjectId: number | null;
    truckDrivers: Record<string, number[]>;
    driverTrucks: Record<string, number[]>;
    options: InspectionOptions;
    inspection?: InspectionDetail;
}
