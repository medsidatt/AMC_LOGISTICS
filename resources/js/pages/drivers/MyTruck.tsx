import { Head } from '@inertiajs/react';
import { lazy, Suspense } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import DataTable from '@/components/ui/DataTable';
import {
    Truck as TruckIcon, Route, Gauge, Fuel, Activity, Timer,
    MapPin, Wrench, Shield,
} from 'lucide-react';

const LeafletMap = lazy(() => import('@/components/map/LeafletMap'));

interface MaintenanceRecord {
    id: number;
    maintenance_date: string;
    type: string;
    description: string | null;
    cost: number | null;
}

interface TruckData {
    id: number;
    matricule: string;
    total_kilometers: number;
    is_active: boolean;
    transporter: { id: number; name: string } | null;
    fuel_level: number | null;
    speed: number | null;
    movement_status: string | null;
    latitude: number | null;
    longitude: number | null;
    last_sync: string | null;
    maintenance_level: string;
    maintenances: MaintenanceRecord[];
}

interface Props {
    driver: { id: number; name: string };
    truck: TruckData | null;
    myTripsCount: number;
}

const MOVEMENT_LABEL: Record<string, string> = {
    moving: 'En mouvement',
    idle: 'Ralenti',
    parked: 'Stationn\u00e9',
};

const MOVEMENT_COLOR: Record<string, string> = {
    moving: '#10b981',
    idle: '#f59e0b',
    parked: '#6b7280',
};

const MAINT_LABEL: Record<string, string> = {
    green: 'OK',
    yellow: 'Bient\u00f4t',
    red: 'Urgente',
};

export default function MyTruck({ driver, truck: t, myTripsCount }: Props) {
    if (!t) {
        return (
            <AuthenticatedLayout title="Mon camion">
                <Head title="Mon camion" />
                <Card>
                    <div className="flex flex-col items-center py-12 text-center">
                        <TruckIcon size={48} className="text-[var(--color-text-muted)] opacity-40 mb-3" />
                        <h3 className="text-lg font-semibold text-[var(--color-text)] mb-1">Aucun camion assign\u00e9</h3>
                        <p className="text-sm text-[var(--color-text-muted)]">
                            Contactez un administrateur pour vous assigner un camion.
                        </p>
                    </div>
                </Card>
            </AuthenticatedLayout>
        );
    }

    const hasGps = t.latitude !== null && t.longitude !== null;
    const fuelVariant = t.fuel_level !== null
        ? t.fuel_level < 30 ? 'danger' : t.fuel_level < 80 ? 'warning' : 'success'
        : 'muted';

    return (
        <AuthenticatedLayout title="Mon camion">
            <Head title="Mon camion" />

            {/* ── Truck header ── */}
            <div className="rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-5 mb-5">
                <div className="flex items-center gap-4 mb-4">
                    <div className="w-16 h-16 rounded-2xl bg-[var(--color-primary)]/10 flex items-center justify-center">
                        <TruckIcon size={28} className="text-[var(--color-primary)]" />
                    </div>
                    <div className="flex-1">
                        <h2 className="text-2xl font-bold text-[var(--color-text)]">{t.matricule}</h2>
                        <div className="flex items-center gap-2 mt-1">
                            {t.transporter && (
                                <span className="text-sm text-[var(--color-text-muted)]">{t.transporter.name}</span>
                            )}
                            <Badge variant={t.is_active ? 'success' : 'muted'}>
                                {t.is_active ? 'Actif' : 'Inactif'}
                            </Badge>
                            {t.movement_status && (
                                <Badge variant={t.movement_status === 'moving' ? 'success' : t.movement_status === 'idle' ? 'warning' : 'muted'}>
                                    {MOVEMENT_LABEL[t.movement_status] ?? t.movement_status}
                                </Badge>
                            )}
                        </div>
                    </div>
                </div>

                {/* Telemetry grid */}
                <div className="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3">
                    <TelemetryCard icon={Gauge} color="text-blue-500" label="Compteur" value={`${Math.round(t.total_kilometers).toLocaleString('fr-FR')} km`} />
                    <TelemetryCard icon={Route} color="text-emerald-500" label="Mes voyages" value={String(myTripsCount)} />
                    <TelemetryCard icon={Fuel} color="text-amber-500" label="Carburant" value={t.fuel_level !== null ? `${t.fuel_level.toFixed(0)} L` : '-'} badge={fuelVariant} />
                    <TelemetryCard icon={Activity} color="text-purple-500" label="Vitesse" value={t.speed !== null ? `${t.speed.toFixed(0)} km/h` : '-'} />
                    <TelemetryCard
                        icon={Wrench}
                        color={t.maintenance_level === 'red' ? 'text-red-500' : t.maintenance_level === 'yellow' ? 'text-amber-500' : 'text-emerald-500'}
                        label="Maintenance"
                        value={MAINT_LABEL[t.maintenance_level] ?? '-'}
                        badge={t.maintenance_level === 'red' ? 'danger' : t.maintenance_level === 'yellow' ? 'warning' : 'success'}
                    />
                    <TelemetryCard icon={Timer} color="text-[var(--color-text-muted)]" label="Sync GPS" value={t.last_sync ?? '-'} small />
                </div>
            </div>

            {/* ── Map + Maintenance side by side ── */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
                {/* Position map */}
                <Card padding={false}>
                    <div className="px-5 pt-4 pb-2">
                        <h3 className="text-sm font-semibold text-[var(--color-text)] flex items-center gap-2">
                            <MapPin size={16} /> Derni\u00e8re position
                        </h3>
                    </div>
                    <div className="px-5 pb-5">
                        {hasGps ? (
                            <Suspense fallback={<div className="h-[280px] rounded-xl bg-[var(--color-surface-hover)] animate-pulse" />}>
                                <LeafletMap
                                    height={280}
                                    markers={[{
                                        id: t.id,
                                        latitude: t.latitude!,
                                        longitude: t.longitude!,
                                        color: MOVEMENT_COLOR[t.movement_status ?? ''] ?? '#6b7280',
                                        popup: (
                                            <div>
                                                <strong>{t.matricule}</strong>
                                                <br />
                                                <span style={{ fontSize: 12 }}>{t.last_sync}</span>
                                            </div>
                                        ),
                                    }]}
                                />
                            </Suspense>
                        ) : (
                            <div className="h-[280px] rounded-xl bg-[var(--color-surface-hover)] flex items-center justify-center text-[var(--color-text-muted)]">
                                <div className="text-center">
                                    <MapPin size={32} className="mx-auto mb-2 opacity-40" />
                                    <p className="text-sm">Position GPS non disponible</p>
                                </div>
                            </div>
                        )}
                    </div>
                </Card>

                {/* Maintenance history */}
                <Card>
                    <h3 className="text-sm font-semibold text-[var(--color-text)] flex items-center gap-2 mb-4">
                        <Wrench size={16} /> Historique maintenance
                    </h3>
                    {(t.maintenances ?? []).length > 0 ? (
                        <div className="space-y-2">
                            {t.maintenances.map((m) => (
                                <div key={m.id} className="flex items-center gap-3 p-3 rounded-xl bg-[var(--color-surface-hover)]">
                                    <div className="w-10 h-10 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center shrink-0">
                                        <Shield size={16} className="text-emerald-600" />
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <div className="text-sm font-medium text-[var(--color-text)]">{m.type ?? 'G\u00e9n\u00e9ral'}</div>
                                        <div className="text-xs text-[var(--color-text-muted)]">{m.maintenance_date}</div>
                                    </div>
                                    {m.description && (
                                        <span className="text-xs text-[var(--color-text-muted)] hidden sm:block truncate max-w-[120px]">{m.description}</span>
                                    )}
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="text-sm text-[var(--color-text-muted)] text-center py-6">Aucune maintenance enregistr\u00e9e</p>
                    )}
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}

/* ── Telemetry card sub-component ── */
function TelemetryCard({ icon: Icon, color, label, value, badge, small }: {
    icon: typeof Gauge;
    color: string;
    label: string;
    value: string;
    badge?: 'success' | 'warning' | 'danger' | 'muted';
    small?: boolean;
}) {
    return (
        <div className="flex items-center gap-2 px-3 py-2.5 rounded-xl bg-[var(--color-surface-hover)]">
            <Icon size={16} className={`${color} shrink-0`} />
            <div className="min-w-0">
                <div className="text-[10px] uppercase text-[var(--color-text-muted)] font-medium">{label}</div>
                {badge ? (
                    <Badge variant={badge} size="sm">{value}</Badge>
                ) : (
                    <div className={`${small ? 'text-xs' : 'text-sm'} font-bold text-[var(--color-text)] truncate`}>{value}</div>
                )}
            </div>
        </div>
    );
}
