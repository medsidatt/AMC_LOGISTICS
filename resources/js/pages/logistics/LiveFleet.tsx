import { Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import LeafletMap, { type MapMarker, type MapCircle } from '@/components/map/LeafletMap';
import StatusBadge, { statusLabel, type LiveStatus } from '@/components/live-fleet/StatusBadge';
import EventFeed, { type DispatchEvent } from '@/components/live-fleet/EventFeed';
import EtaCell from '@/components/live-fleet/EtaCell';
import { usePolling } from '@/hooks/usePolling';
import { RefreshCw, X, Truck as TruckIcon, MapPin } from 'lucide-react';

interface DispatchRow {
    id: number;
    dispatch_date: string | null;
    driver: { id: number; name: string } | null;
    truck: {
        id: number;
        matricule: string;
        latitude: number | null;
        longitude: number | null;
        speed_kmh: number | null;
        fuel_litres: number | null;
        ignition_on: boolean | null;
        device_last_seen_at: string | null;
    } | null;
    wish_provider: { id: number; name: string } | null;
    current_status: LiveStatus;
    current_status_at: string | null;
    current_place: { id: number; name: string; type: string } | null;
    eta_at: string | null;
    last_event: DispatchEvent | null;
    notification_status: string | null;
}

interface PlaceFence {
    id: number;
    name: string;
    type: string;
    latitude: number;
    longitude: number;
    radius_m: number;
}

interface Props {
    date: string;
    dispatches: DispatchRow[];
    events: DispatchEvent[];
    places: PlaceFence[];
}

const PLACE_COLOR: Record<string, string> = {
    base: '#64748b',
    provider_site: '#3b82f6',
    client_site: '#a855f7',
    fuel_station: '#06b6d4',
    border_post: '#6366f1',
};

const STATUS_PIN_COLOR: Record<string, string> = {
    EN_ROUTE: '#10b981',
    RETOUR: '#34d399',
    CHARGEMENT: '#f59e0b',
    FILE_CARRIERE: '#3b82f6',
    CHEZ_CLIENT: '#a855f7',
    RAVITAILLEMENT: '#06b6d4',
    PASSAGE_FRONTIERE: '#6366f1',
    A_LA_BASE: '#64748b',
    ARRET_LONG: '#ef4444',
    ARRET: '#9ca3af',
    OFFLINE: '#6b7280',
    TERMINE: '#94a3b8',
};

function relativeTime(iso: string | null): string {
    if (!iso) return '—';
    const diff = Math.round((Date.now() - new Date(iso).getTime()) / 60000);
    if (diff < 1) return 'à l\'instant';
    if (diff < 60) return `il y a ${diff} min`;
    const h = Math.floor(diff / 60);
    if (h < 24) return `il y a ${h}h`;
    return `il y a ${Math.floor(h / 24)}j`;
}

export default function LiveFleet({ date, dispatches, events, places }: Props) {
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [drawerData, setDrawerData] = useState<{
        dispatch: DispatchRow;
        events: DispatchEvent[];
        snapshots: Array<{
            id: number;
            recorded_at: string | null;
            fuel_litres: number | null;
            speed_kmh: number | null;
            ignition_on: boolean | null;
        }>;
    } | null>(null);

    // Poll the page every 60s for global updates
    usePolling({ interval: 60, only: ['dispatches', 'events'] });

    // When a drawer is open, also refresh its content every 30s
    useEffect(() => {
        if (!selectedId) {
            setDrawerData(null);
            return;
        }
        let cancelled = false;
        const fetchDrawer = () => {
            fetch(`/logistics/live/${selectedId}`, { headers: { Accept: 'application/json' } })
                .then((r) => r.json())
                .then((d) => {
                    if (!cancelled) setDrawerData(d);
                })
                .catch(() => {});
        };
        fetchDrawer();
        const id = setInterval(fetchDrawer, 30000);
        return () => {
            cancelled = true;
            clearInterval(id);
        };
    }, [selectedId]);

    const stats = useMemo(() => ({
        total: dispatches.length,
        en_route: dispatches.filter((d) => d.current_status === 'EN_ROUTE' || d.current_status === 'RETOUR').length,
        at_quarry: dispatches.filter((d) => d.current_status === 'FILE_CARRIERE' || d.current_status === 'CHARGEMENT').length,
        stopped: dispatches.filter((d) => d.current_status === 'ARRET_LONG').length,
        offline: dispatches.filter((d) => d.current_status === 'OFFLINE').length,
    }), [dispatches]);

    const markers: MapMarker[] = useMemo(() => (
        dispatches
            .filter((d) => d.truck?.latitude !== null && d.truck?.latitude !== undefined)
            .map((d) => ({
                id: d.id,
                latitude: d.truck!.latitude!,
                longitude: d.truck!.longitude!,
                color: STATUS_PIN_COLOR[d.current_status ?? 'ARRET'] ?? '#3b82f6',
                popup: (
                    <div style={{ minWidth: 200 }}>
                        <strong style={{ fontSize: 14 }}>{d.truck?.matricule ?? '—'}</strong>
                        <div style={{ fontSize: 12, marginTop: 6, color: '#6b7280' }}>
                            {d.driver?.name ?? '—'}
                        </div>
                        <div style={{ fontSize: 12, marginTop: 4 }}>{statusLabel(d.current_status)}</div>
                        {d.current_place && (
                            <div style={{ fontSize: 12, color: '#6b7280' }}>{d.current_place.name}</div>
                        )}
                    </div>
                ),
            }))
    ), [dispatches]);

    const circles: MapCircle[] = useMemo(() => (
        places.map((p) => ({
            id: `place-${p.id}`,
            latitude: p.latitude,
            longitude: p.longitude,
            radiusMeters: p.radius_m,
            color: PLACE_COLOR[p.type] ?? '#94a3b8',
            fillOpacity: 0.1,
            popup: (
                <div style={{ fontSize: 12 }}>
                    <strong>{p.name}</strong>
                    <div style={{ color: '#6b7280' }}>{p.type}</div>
                </div>
            ),
        }))
    ), [places]);

    const refresh = () => router.reload({ only: ['dispatches', 'events'] });

    const selected = drawerData?.dispatch ?? dispatches.find((d) => d.id === selectedId) ?? null;

    return (
        <AuthenticatedLayout title="Suivi en direct">
            <Head title="Suivi en direct de la flotte" />

            <div className="space-y-4">
                {/* Header + stats */}
                <div className="flex flex-wrap items-center gap-3 justify-between">
                    <div>
                        <h1 className="text-xl font-semibold flex items-center gap-2">
                            <TruckIcon size={20} className="text-emerald-500" />
                            Suivi en direct — {date}
                        </h1>
                        <p className="text-sm text-[var(--color-text-muted)]">
                            Programmes du jour, mis à jour en continu depuis Fleeti.
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button variant="secondary" size="sm" onClick={refresh}>
                            <RefreshCw size={14} className="mr-1.5" />
                            Rafraîchir
                        </Button>
                    </div>
                </div>

                <div className="grid grid-cols-2 md:grid-cols-5 gap-2">
                    <StatCard label="Programmes du jour" value={stats.total} color="text-blue-500" />
                    <StatCard label="En route / Retour" value={stats.en_route} color="text-emerald-500" />
                    <StatCard label="À la carrière" value={stats.at_quarry} color="text-amber-500" />
                    <StatCard label="Arrêts longs" value={stats.stopped} color="text-red-500" />
                    <StatCard label="Hors ligne" value={stats.offline} color="text-gray-500" />
                </div>

                {/* Two-column layout */}
                <div className="grid grid-cols-1 lg:grid-cols-5 gap-4">
                    {/* Left: table */}
                    <Card padding={false} className="lg:col-span-2">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-[var(--color-surface-hover)] text-xs uppercase text-[var(--color-text-muted)]">
                                    <tr>
                                        <th className="text-left px-3 py-2">Conducteur · Camion</th>
                                        <th className="text-left px-3 py-2">Statut</th>
                                        <th className="text-left px-3 py-2">Dernier événement</th>
                                        <th className="text-left px-3 py-2">ETA</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {dispatches.length === 0 ? (
                                        <tr><td colSpan={4} className="px-3 py-8 text-center text-[var(--color-text-muted)]">
                                            Aucun programme actif aujourd'hui.
                                        </td></tr>
                                    ) : dispatches.map((d) => (
                                        <tr
                                            key={d.id}
                                            onClick={() => setSelectedId(d.id)}
                                            className={`border-t border-[var(--color-border)] cursor-pointer hover:bg-[var(--color-surface-hover)] ${selectedId === d.id ? 'bg-[var(--color-surface-hover)]' : ''}`}
                                        >
                                            <td className="px-3 py-2">
                                                <div className="font-medium">{d.driver?.name ?? '—'}</div>
                                                <div className="text-xs text-[var(--color-text-muted)]">{d.truck?.matricule ?? 'Pas de camion'}</div>
                                            </td>
                                            <td className="px-3 py-2">
                                                <StatusBadge status={d.current_status} />
                                                {d.current_place && (
                                                    <div className="text-xs text-[var(--color-text-muted)] mt-0.5 flex items-center gap-1">
                                                        <MapPin size={10} />{d.current_place.name}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-3 py-2 text-xs text-[var(--color-text-muted)]">
                                                {d.last_event ? (
                                                    <div className="flex flex-col">
                                                        <span>{d.last_event.type}</span>
                                                        <span>{relativeTime(d.last_event.occurred_at)}</span>
                                                    </div>
                                                ) : relativeTime(d.current_status_at)}
                                            </td>
                                            <td className="px-3 py-2"><EtaCell etaAt={d.eta_at} /></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Card>

                    {/* Right: map */}
                    <Card padding={false} className="lg:col-span-3">
                        <div className="p-3">
                            <LeafletMap markers={markers} circles={circles} height={560} />
                        </div>
                    </Card>
                </div>
            </div>

            {/* Drawer */}
            {selected && (
                <div className="fixed inset-y-0 right-0 w-full md:w-[480px] bg-[var(--color-surface)] border-l border-[var(--color-border)] shadow-2xl z-50 overflow-y-auto">
                    <div className="sticky top-0 bg-[var(--color-surface)] border-b border-[var(--color-border)] px-4 py-3 flex items-center justify-between">
                        <div>
                            <div className="text-sm font-semibold">{selected.driver?.name ?? '—'}</div>
                            <div className="text-xs text-[var(--color-text-muted)]">{selected.truck?.matricule ?? 'Pas de camion'}</div>
                        </div>
                        <button
                            onClick={() => setSelectedId(null)}
                            className="text-[var(--color-text-muted)] hover:text-[var(--color-text)] p-1"
                            aria-label="Fermer"
                        >
                            <X size={18} />
                        </button>
                    </div>

                    <div className="p-4 space-y-4">
                        <div className="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <div className="text-xs text-[var(--color-text-muted)] uppercase">Statut</div>
                                <div className="mt-1"><StatusBadge status={selected.current_status} size="md" /></div>
                            </div>
                            <div>
                                <div className="text-xs text-[var(--color-text-muted)] uppercase">ETA</div>
                                <div className="mt-1"><EtaCell etaAt={selected.eta_at} /></div>
                            </div>
                            <div>
                                <div className="text-xs text-[var(--color-text-muted)] uppercase">Carrière souhaitée</div>
                                <div className="mt-1 text-sm">{selected.wish_provider?.name ?? '—'}</div>
                            </div>
                            <div>
                                <div className="text-xs text-[var(--color-text-muted)] uppercase">Carburant</div>
                                <div className="mt-1 text-sm">
                                    {selected.truck?.fuel_litres !== null && selected.truck?.fuel_litres !== undefined
                                        ? `${selected.truck.fuel_litres.toFixed(0)} L`
                                        : '—'}
                                </div>
                            </div>
                        </div>

                        <div>
                            <div className="text-xs uppercase text-[var(--color-text-muted)] mb-2">Chronologie</div>
                            <EventFeed events={drawerData?.events ?? []} />
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}

function StatCard({ label, value, color }: { label: string; value: number; color: string }) {
    return (
        <Card>
            <div className="text-xs text-[var(--color-text-muted)] uppercase">{label}</div>
            <div className={`text-2xl font-bold ${color}`}>{value}</div>
        </Card>
    );
}
