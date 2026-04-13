import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import LeafletMap from '@/components/map/LeafletMap';
import { Truck as TruckIcon, Activity, Timer, Fuel } from 'lucide-react';

interface FleetTruck {
    id: number;
    matricule: string;
    latitude: number;
    longitude: number;
    heading: number | null;
    speed: number | null;
    movement_status: string | null;
    ignition_on: boolean | null;
    fuel_level: number | null;
    last_sync: string | null;
}

interface Props {
    trucks: FleetTruck[];
}

const STATUS_COLOR: Record<string, string> = {
    moving: '#10b981',   // emerald
    idle: '#f59e0b',     // amber
    parked: '#6b7280',   // gray
    unknown: '#94a3b8',  // slate
};

const STATUS_LABEL: Record<string, string> = {
    moving: 'En mouvement',
    idle: 'Ralenti',
    parked: 'À l\'arrêt',
    unknown: 'Inconnu',
};

export default function FleetMap({ trucks }: Props) {
    const stats = {
        total: trucks.length,
        moving: trucks.filter((t) => t.movement_status === 'moving').length,
        idle: trucks.filter((t) => t.movement_status === 'idle').length,
        parked: trucks.filter((t) => t.movement_status === 'parked').length,
    };

    const markers = trucks.map((t) => {
        const color = STATUS_COLOR[t.movement_status ?? 'unknown'] ?? '#3b82f6';
        return {
            id: t.id,
            latitude: t.latitude,
            longitude: t.longitude,
            color,
            popup: (
                <div style={{ minWidth: 180 }}>
                    <strong style={{ fontSize: 14 }}>{t.matricule}</strong>
                    <div style={{ fontSize: 12, marginTop: 6 }}>
                        <div>État: {STATUS_LABEL[t.movement_status ?? 'unknown'] ?? t.movement_status ?? '-'}</div>
                        {t.speed !== null && <div>Vitesse: {t.speed.toFixed(0)} km/h</div>}
                        {t.fuel_level !== null && <div>Carburant: {t.fuel_level.toFixed(0)} L</div>}
                        {t.last_sync && <div>Sync: {t.last_sync}</div>}
                    </div>
                    <a
                        href={`/trucks/${t.id}/show-page`}
                        style={{ fontSize: 12, color: '#2563eb', marginTop: 6, display: 'inline-block' }}
                    >
                        Détails du camion →
                    </a>
                </div>
            ),
        };
    });

    return (
        <AuthenticatedLayout title="Cartographie de la flotte">
            <Head title="Carte de la flotte" />

            <div className="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
                <Card>
                    <div className="flex items-center gap-3">
                        <div className="p-2 rounded-lg bg-blue-100 dark:bg-blue-900/30 text-blue-600">
                            <TruckIcon size={20} />
                        </div>
                        <div>
                            <div className="text-xs text-[var(--color-text-muted)] uppercase">Camions actifs</div>
                            <div className="text-2xl font-bold text-[var(--color-text)]">{stats.total}</div>
                        </div>
                    </div>
                </Card>
                <Card>
                    <div className="flex items-center gap-3">
                        <div className="p-2 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600">
                            <Activity size={20} />
                        </div>
                        <div>
                            <div className="text-xs text-[var(--color-text-muted)] uppercase">En mouvement</div>
                            <div className="text-2xl font-bold text-[var(--color-text)]">{stats.moving}</div>
                        </div>
                    </div>
                </Card>
                <Card>
                    <div className="flex items-center gap-3">
                        <div className="p-2 rounded-lg bg-amber-100 dark:bg-amber-900/30 text-amber-600">
                            <Timer size={20} />
                        </div>
                        <div>
                            <div className="text-xs text-[var(--color-text-muted)] uppercase">Ralenti</div>
                            <div className="text-2xl font-bold text-[var(--color-text)]">{stats.idle}</div>
                        </div>
                    </div>
                </Card>
                <Card>
                    <div className="flex items-center gap-3">
                        <div className="p-2 rounded-lg bg-gray-100 dark:bg-gray-900/30 text-gray-600">
                            <Fuel size={20} />
                        </div>
                        <div>
                            <div className="text-xs text-[var(--color-text-muted)] uppercase">À l'arrêt</div>
                            <div className="text-2xl font-bold text-[var(--color-text)]">{stats.parked}</div>
                        </div>
                    </div>
                </Card>
            </div>

            <Card padding={false} className="mb-5">
                <div className="p-5">
                    {markers.length === 0 ? (
                        <div className="text-center py-12 text-[var(--color-text-muted)]">
                            Aucun camion avec position GPS disponible.
                        </div>
                    ) : (
                        <LeafletMap markers={markers} height={560} />
                    )}
                </div>
            </Card>

            <Card header="Liste des camions" padding={false}>
                <div className="p-5">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-xs uppercase text-[var(--color-text-muted)] border-b border-[var(--color-border)]">
                                    <th className="text-left py-2">Matricule</th>
                                    <th className="text-left py-2">État</th>
                                    <th className="text-left py-2">Vitesse</th>
                                    <th className="text-left py-2">Carburant</th>
                                    <th className="text-left py-2">Dernière sync</th>
                                    <th className="text-right py-2">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--color-border)]">
                                {trucks.map((t) => (
                                    <tr key={t.id} className="hover:bg-[var(--color-surface-hover)]">
                                        <td className="py-2 font-semibold">{t.matricule}</td>
                                        <td className="py-2">
                                            <Badge
                                                variant={
                                                    t.movement_status === 'moving' ? 'success' :
                                                    t.movement_status === 'idle' ? 'warning' :
                                                    t.movement_status === 'parked' ? 'muted' :
                                                    'info'
                                                }
                                            >
                                                {STATUS_LABEL[t.movement_status ?? 'unknown'] ?? '-'}
                                            </Badge>
                                        </td>
                                        <td className="py-2">{t.speed !== null ? `${t.speed.toFixed(0)} km/h` : '-'}</td>
                                        <td className="py-2">{t.fuel_level !== null ? `${t.fuel_level.toFixed(0)} L` : '-'}</td>
                                        <td className="py-2 text-[var(--color-text-muted)]">{t.last_sync ?? '-'}</td>
                                        <td className="py-2 text-right">
                                            <a href={`/trucks/${t.id}/show-page`} className="text-[var(--color-primary)] hover:underline">
                                                Détails
                                            </a>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </Card>
        </AuthenticatedLayout>
    );
}
