import { Head, Link } from '@inertiajs/react';
import { lazy, Suspense } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import { usePermission } from '@/hooks/usePermission';
import {
    Truck as TruckIcon, Route, Gauge, Fuel, Activity, Timer,
    MapPin, Wrench, Shield, AlertTriangle, ClipboardCheck,
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
    parked: 'Stationné',
};
const MOVEMENT_COLOR: Record<string, string> = {
    moving: '#10b981', idle: '#f59e0b', parked: '#6b7280',
};
const MOVEMENT_VARIANT: Record<string, 'success' | 'warning' | 'muted'> = {
    moving: 'success', idle: 'warning', parked: 'muted',
};

const MAINT_LABEL: Record<string, string> = { green: 'OK', yellow: 'Bientôt', red: 'Urgente' };
const MAINT_VARIANT: Record<string, 'success' | 'warning' | 'danger'> = { green: 'success', yellow: 'warning', red: 'danger' };

function SectionLabel({ children }: { children: React.ReactNode }) {
    return (
        <div className="text-xs uppercase tracking-wider font-semibold text-[var(--color-text-muted)] mt-2 mb-1">
            {children}
        </div>
    );
}

function TelemetryTile({ icon, color, label, value, badge }: {
    icon: React.ReactNode;
    color: string;
    label: string;
    value: string;
    badge?: 'success' | 'warning' | 'danger' | 'muted';
}) {
    return (
        <Card>
            <div className="flex items-center gap-2">
                <div className={`${color} shrink-0`}>{icon}</div>
                <div className="min-w-0 flex-1">
                    <div className="text-[10px] uppercase text-[var(--color-text-muted)] font-medium">{label}</div>
                    {badge ? (
                        <Badge variant={badge} size="sm">{value}</Badge>
                    ) : (
                        <div className="text-sm font-bold truncate">{value}</div>
                    )}
                </div>
            </div>
        </Card>
    );
}

export default function MyTruck({ driver, truck: t, myTripsCount }: Props) {
    const { isAdmin } = usePermission();
    if (!t) {
        return (
            <AuthenticatedLayout title="Mon camion">
                <Head title="Mon camion" />
                <Card>
                    <div className="flex flex-col items-center py-12 text-center">
                        <TruckIcon size={48} className="text-[var(--color-text-muted)] opacity-40 mb-3" />
                        <h3 className="text-lg font-semibold mb-1">Aucun camion assigné</h3>
                        <p className="text-sm text-[var(--color-text-muted)]">
                            Contactez un administrateur pour vous assigner un camion.
                        </p>
                    </div>
                </Card>
            </AuthenticatedLayout>
        );
    }

    const hasGps = t.latitude !== null && t.longitude !== null;
    const fuelVariant: 'success' | 'warning' | 'danger' | 'muted' = t.fuel_level !== null
        ? t.fuel_level < 30 ? 'danger' : t.fuel_level < 80 ? 'warning' : 'success'
        : 'muted';

    return (
        <AuthenticatedLayout title="Mon camion">
            <Head title="Mon camion" />

            <div className="space-y-4">
                {/* ── Header ── */}
                <Card>
                    <div className="flex flex-wrap items-center gap-4">
                        <div className="w-16 h-16 rounded-2xl bg-[var(--color-primary)]/10 flex items-center justify-center shrink-0">
                            <TruckIcon size={28} className="text-[var(--color-primary)]" />
                        </div>
                        <div className="flex-1 min-w-0">
                            <h2 className="text-2xl font-bold truncate">{t.matricule}</h2>
                            <div className="flex items-center gap-2 mt-1 flex-wrap">
                                {t.transporter && (
                                    <span className="text-sm text-[var(--color-text-muted)]">{t.transporter.name}</span>
                                )}
                                <Badge variant={t.is_active ? 'success' : 'muted'}>{t.is_active ? 'Actif' : 'Inactif'}</Badge>
                                {t.movement_status && (
                                    <Badge variant={MOVEMENT_VARIANT[t.movement_status] ?? 'muted'}>
                                        {MOVEMENT_LABEL[t.movement_status] ?? t.movement_status}
                                    </Badge>
                                )}
                                <Badge variant={MAINT_VARIANT[t.maintenance_level] ?? 'muted'}>
                                    Maintenance : {MAINT_LABEL[t.maintenance_level] ?? '—'}
                                </Badge>
                            </div>
                        </div>
                    </div>
                </Card>

                {/* ── Quick actions ── */}
                <SectionLabel>Actions rapides</SectionLabel>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <Link href="/drivers/checklist-page" className="block">
                        <Card className="hover:bg-[var(--color-surface-hover)] transition cursor-pointer h-full">
                            <div className="flex items-center gap-3">
                                <div className="p-2.5 rounded-lg bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400">
                                    <ClipboardCheck size={18} />
                                </div>
                                <div>
                                    <div className="font-semibold text-sm">Checklist hebdo</div>
                                    <div className="text-xs text-[var(--color-text-muted)]">Vérifier le véhicule</div>
                                </div>
                            </div>
                        </Card>
                    </Link>
                    <Link href="/drivers/issues" className="block">
                        <Card className="hover:bg-[var(--color-surface-hover)] transition cursor-pointer h-full">
                            <div className="flex items-center gap-3">
                                <div className="p-2.5 rounded-lg bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400">
                                    <AlertTriangle size={18} />
                                </div>
                                <div>
                                    <div className="font-semibold text-sm">Signaler un problème</div>
                                    <div className="text-xs text-[var(--color-text-muted)]">Panne ou défaut</div>
                                </div>
                            </div>
                        </Card>
                    </Link>
                    <Link href="/drivers/my-trips" className="block">
                        <Card className="hover:bg-[var(--color-surface-hover)] transition cursor-pointer h-full">
                            <div className="flex items-center gap-3">
                                <div className="p-2.5 rounded-lg bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                                    <Route size={18} />
                                </div>
                                <div>
                                    <div className="font-semibold text-sm">Mes voyages</div>
                                    <div className="text-xs text-[var(--color-text-muted)]">{myTripsCount} rotation{myTripsCount > 1 ? 's' : ''} ce mois</div>
                                </div>
                            </div>
                        </Card>
                    </Link>
                </div>

                {/* ── Télémétrie ── */}
                <SectionLabel>Télémétrie</SectionLabel>
                <div className={`grid grid-cols-2 sm:grid-cols-3 ${isAdmin ? 'lg:grid-cols-5' : 'lg:grid-cols-4'} gap-3`}>
                    <TelemetryTile icon={<Gauge size={18} />} color="text-blue-500" label="Compteur" value={`${Math.round(t.total_kilometers).toLocaleString('fr-FR')} km`} />
                    <TelemetryTile icon={<Fuel size={18} />} color="text-amber-500" label="Carburant" value={t.fuel_level !== null ? `${t.fuel_level.toFixed(0)} L` : '—'} badge={fuelVariant} />
                    <TelemetryTile icon={<Activity size={18} />} color="text-purple-500" label="Vitesse" value={t.speed !== null ? `${t.speed.toFixed(0)} km/h` : '—'} />
                    <TelemetryTile
                        icon={<Wrench size={18} />}
                        color={t.maintenance_level === 'red' ? 'text-red-500' : t.maintenance_level === 'yellow' ? 'text-amber-500' : 'text-emerald-500'}
                        label="Maintenance"
                        value={MAINT_LABEL[t.maintenance_level] ?? '—'}
                        badge={MAINT_VARIANT[t.maintenance_level] ?? 'muted'}
                    />
                    {isAdmin && (
                        <TelemetryTile icon={<Timer size={18} />} color="text-[var(--color-text-muted)]" label="Sync GPS" value={t.last_sync ?? '—'} />
                    )}
                </div>

                {/* ── Position (admin only) + Maintenance ── */}
                <div className={`grid grid-cols-1 ${isAdmin ? 'lg:grid-cols-2' : ''} gap-4`}>
                    {isAdmin && (
                        <Card padding={false}>
                            <div className="px-5 pt-4 pb-2">
                                <h3 className="text-sm font-semibold flex items-center gap-2">
                                    <MapPin size={16} /> Dernière position
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
                    )}

                    <Card>
                        <h3 className="text-sm font-semibold flex items-center gap-2 mb-4">
                            <Wrench size={16} /> Historique maintenance
                        </h3>
                        {(t.maintenances ?? []).length > 0 ? (
                            <div className="space-y-2">
                                {t.maintenances.map((m) => (
                                    <div key={m.id} className="flex items-center gap-3 p-3 rounded-xl bg-[var(--color-surface-hover)]">
                                        <div className="w-9 h-9 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center shrink-0">
                                            <Shield size={14} className="text-emerald-600" />
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <div className="text-sm font-medium truncate">{m.type ?? 'Général'}</div>
                                            <div className="text-xs text-[var(--color-text-muted)]">{m.maintenance_date}</div>
                                        </div>
                                        {m.description && (
                                            <span className="text-xs text-[var(--color-text-muted)] hidden sm:block truncate max-w-[120px]" title={m.description}>{m.description}</span>
                                        )}
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-sm text-[var(--color-text-muted)] text-center py-6">Aucune maintenance enregistrée</p>
                        )}
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
