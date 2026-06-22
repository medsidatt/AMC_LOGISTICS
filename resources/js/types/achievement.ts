export interface TruckAchievement {
    truck_id: number;
    matricule: string;
    capacity_tonnage: number;
    target_rotations: number;
    target_tons: number;
    ticketed_rotations: number;
    ticketed_tons: number;
    gps_only_rotations: number;
    gps_only_tons: number;
    done_rotations: number;
    done_tons: number;
    remaining_rotations: number;
    remaining_tons: number;
    pct: number | null;
    avg_load_t: number;
    fill_pct: number | null;
    missing_tickets: number;
}

export interface FleetAchievement {
    target_rotations: number;
    target_tons: number;
    ticketed_rotations: number;
    ticketed_tons: number;
    gps_only_rotations: number;
    gps_only_tons: number;
    done_rotations: number;
    done_tons: number;
    remaining_rotations: number;
    remaining_tons: number;
    pct: number | null;
    avg_load_t: number;
    fill_pct: number | null;
    missing_tickets: number;
}

export interface Projection {
    days_elapsed: number;
    days_total: number;
    days_remaining: number;
    pace_rotations_per_day: number;
    projected_rotations: number;
    projected_tons: number;
    on_track: boolean;
}

export type PlanningMode = 'WEEK' | 'MONTH' | 'YEAR' | 'CUSTOM';

export type TargetSource = 'exact' | 'derived' | 'aggregated' | 'none';

export interface Achievement {
    period: { start: string; end: string };
    gps_available: boolean;
    has_objective: boolean;
    /** How the target was resolved: exact match, prorated from a broader objective, or aggregated. */
    target_source?: TargetSource;
    /** Fraction (0–1) of the period covered by objectives (for aggregated targets). */
    target_coverage?: number;
    fleet: FleetAchievement;
    projection: Projection;
    per_truck: TruckAchievement[];
    leaderboard: { top: TruckAchievement[]; bottom: TruckAchievement[] };
    missing_ticket_list: { truck_id: number; matricule: string; date: string; distance_km: number }[];
}
