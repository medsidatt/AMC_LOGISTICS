import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import LeafletMap, { MapMarker } from '@/components/map/LeafletMap';
import { ArrowLeft, MapPin, Fuel, Gauge, AlertTriangle } from 'lucide-react';

interface Transport {
    id: number;
    reference: string;
    provider_date: string | null;
    client_date: string | null;
    provider_net_weight: number | null;
    client_net_weight: number | null;
    gap: number | null;
    start_km: number | null;
    end_km: number | null;
    truck: { id: number; matricule: string } | null;
    provider: { id: number; name: string } | null;
}

interface TrailPoint {
    id: number;
    recorded_at: string;
    latitude: number;
    longitude: number;
    speed_kmh: number | null;
    fuel_litres: number | null;
    odometer_km: number | null;
    movement_status: string | null;
    ignition_on: boolean | null;
}

interface Stop {
    id: number;
    started_at: string;
    ended_at: string;
    duration_minutes: number;
    latitude: number;
    longitude: number;
    classification: string | null;
    place: { id: number; name: string; type: string } | null;
    fuel_delta_litres: number | null;
}

interface Incident {
    id: number;
    type: string;
    severity: string;
    status: string;
    title: string;
    detected_at: string | null;
    latitude: number | null;
    longitude: number | null;
}

interface Segment {
    id: number;
    started_at: string | null;
    ended_at: string | null;
    distance_km: number | null;
    fuel_consumed_litres: number | null;
    stop_count: number;
    unknown_stop_count: number;
    origin_place: { id: number; name: string; type: string } | null;
    destination_place: { id: number; name: string; type: string } | null;
}

interface ReplayData {
    segment: Segment | null;
    trail: TrailPoint[];
    stops: Stop[];
    incidents: Incident[];
}

interface Props {
    transport: Transport;
    dataUrl: string;
}

const STOP_CLASS_COLOR: Record<string, string> = {
    known_base: '#10b981',
    known_provider: '#3b82f6',
    known_client: '#8b5cf6',
    known_fuel_station: '#06b6d4',
    known_parking: '#64748b',
    unknown: '#ef4444',
    roadside: '#f59e0b',
};

const STOP_CLASS_LABEL: Record<string, string> = {
    known_base: 'Base',
    known_provider: 'Fournisseur',
    known_client: 'Client',
    known_fuel_station: 'Station',
    known_parking: 'Parking',
    unknown: 'Inconnu',
    roadside: 'Bord de route',
};

export default function TripReplay({ transport, dataUrl }: Props) {
    const [data, setData] = useState<ReplayData | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        fetch(dataUrl, { headers: { Accept: 'application/json' } })
            .then((r) => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then((json: ReplayData) => {
                if (!cancelled) setData(json);
            })
            .catch((e) => {
                if (!cancelled) setError(e.message ?? 'Erreur de chargement');
            })
            .finally(() => {
                if (!cancelled) setLoading(false);
            });
        return () => { cancelled = true; };
    }, [dataUrl]);

    const polyline: Array<[number, number]> = (data?.trail ?? []).map((p) => [p.latitude, p.longitude]);

    const markers: MapMarker[] = [];
    (data?.stops ?? []).forEach((s) => {
        markers.push({
            id: `stop-${s.id}`,
            latitude: s.latitude,
            longitude: s.longitude,
            color: STOP_CLASS_COLOR[s.classification ?? 'unknown'] ?? '#64748b',
            popup: (
                <div style={{ minWidth: 160 }}>
                    <strong>Arrêt {s.duration_minutes} min</strong>
                    <div style={{ fontSize: 12, marginTop: 4 }}>
                        <div>{STOP_CLASS_LABEL[s.classification ?? 'unknown']}</div>
                        {s.place && <div>{s.place.name}</div>}
                        <div>{s.started_at}</div>
                    </div>
                </div>
            ),
        });
    });
    (data?.incidents ?? []).forEach((i) => {
        if (i.latitude !== null && i.longitude !== null) {
            markers.push({
                id: `inc-${i.id}`,
                latitude: i.latitude,
                longitude: i.longitude,
                color: '#dc2626',
                popup: (
                    <div style={{ minWidth: 160 }}>
                        <strong style={{ color: '#dc2626' }}>⚠ {i.title}</strong>
                        <br />
                        <span style={{ fontSize: 12 }}>{i.detected_at}</span>
                    </div>
                ),
            });
        }
    });

    // Add start + end markers from the trail
    if (polyline.length > 0) {
        const first = data!.trail[0];
        const last = data!.trail[data!.trail.length - 1];
        markers.push({
            id: 'start',
            latitude: first.latitude,
            longitude: first.longitude,
            color: '#22c55e',
            popup: <strong>Départ — {first.recorded_at}</strong>,
        });
        markers.push({
            id: 'end',
            latitude: last.latitude,
            longitude: last.longitude,
            color: '#0ea5e9',
            popup: <strong>Arrivée — {last.recorded_at}</strong>,
        });
    }

    return (
        <AuthenticatedLayout title={`Reprise — ${transport.reference}`}>
            <Head title={`Reprise ${transport.reference}`} />

            <div className="mb-4">
                <Button
                    variant="ghost"
                    icon={<ArrowLeft size={14} />}
                    onClick={() => router.visit(`/transport_tracking/${transport.id}/show-page`)}
                >
                    Retour à la mission
                </Button>
            </div>

            {/* Summary */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-3 mb-5">
                <Card>
                    <div className="text-xs uppercase text-[var(--color-text-muted)]">Camion</div>
                    <div className="text-lg font-semibold text-[var(--color-text)]">{transport.truck?.matricule ?? '-'}</div>
                </Card>
                <Card>
                    <div className="text-xs uppercase text-[var(--color-text-muted)]">Distance</div>
                    <div className="text-lg font-semibold text-[var(--color-text)] flex items-center gap-2">
                        <Gauge size={16} />
                        {data?.segment?.distance_km ?? '-'} km
                    </div>
                </Card>
                <Card>
                    <div className="text-xs uppercase text-[var(--color-text-muted)]">Carburant consommé</div>
                    <div className="text-lg font-semibold text-[var(--color-text)] flex items-center gap-2">
                        <Fuel size={16} />
                        {data?.segment?.fuel_consumed_litres ?? '-'} L
                    </div>
                </Card>
                <Card>
                    <div className="text-xs uppercase text-[var(--color-text-muted)]">Arrêts</div>
                    <div className="text-lg font-semibold text-[var(--color-text)] flex items-center gap-2">
                        <MapPin size={16} />
                        {data?.segment?.stop_count ?? 0}
                        {(data?.segment?.unknown_stop_count ?? 0) > 0 && (
                            <Badge variant="danger">{data?.segment?.unknown_stop_count} inconnu(s)</Badge>
                        )}
                    </div>
                </Card>
            </div>

            {/* Transport metadata */}
            <Card header="Informations mission" className="mb-5">
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                    <div>
                        <div className="text-xs text-[var(--color-text-muted)] uppercase">Fournisseur</div>
                        <div>{transport.provider?.name ?? '-'}</div>
                        <div className="text-[var(--color-text-muted)] text-xs mt-0.5">{transport.provider_date ?? '-'}</div>
                    </div>
                    <div>
                        <div className="text-xs text-[var(--color-text-muted)] uppercase">Client</div>
                        <div>{transport.client_date ?? '-'}</div>
                    </div>
                    <div>
                        <div className="text-xs text-[var(--color-text-muted)] uppercase">Poids net (fournisseur → client)</div>
                        <div>
                            {transport.provider_net_weight ?? '-'} → {transport.client_net_weight ?? '-'} kg
                            {transport.gap !== null && (
                                <span className={transport.gap < 0 ? 'text-red-500 ml-2' : 'text-emerald-500 ml-2'}>
                                    ({transport.gap > 0 ? '+' : ''}{transport.gap} kg)
                                </span>
                            )}
                        </div>
                    </div>
                </div>
            </Card>

            {/* Map */}
            <Card padding={false} className="mb-5">
                <div className="p-5">
                    {loading && <div className="text-center py-12 text-[var(--color-text-muted)]">Chargement du trajet…</div>}
                    {error && <div className="text-center py-12 text-red-500">Erreur: {error}</div>}
                    {!loading && !error && polyline.length === 0 && (
                        <div className="text-center py-12 text-[var(--color-text-muted)]">
                            Aucune donnée de télémétrie disponible pour cette mission.
                        </div>
                    )}
                    {!loading && !error && polyline.length > 0 && (
                        <LeafletMap
                            height={520}
                            markers={markers}
                            polyline={polyline}
                            polylineColor="#3b82f6"
                        />
                    )}
                </div>
            </Card>

            {/* Stops */}
            {data && data.stops.length > 0 && (
                <Card header={`Arrêts (${data.stops.length})`} padding={false} className="mb-5">
                    <div className="p-5">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-xs uppercase text-[var(--color-text-muted)] border-b border-[var(--color-border)]">
                                        <th className="text-left py-2">Début</th>
                                        <th className="text-left py-2">Fin</th>
                                        <th className="text-left py-2">Durée</th>
                                        <th className="text-left py-2">Classification</th>
                                        <th className="text-left py-2">Lieu</th>
                                        <th className="text-right py-2">Δ Carburant</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-[var(--color-border)]">
                                    {data.stops.map((s) => (
                                        <tr key={s.id}>
                                            <td className="py-2">{s.started_at}</td>
                                            <td className="py-2">{s.ended_at}</td>
                                            <td className="py-2">{s.duration_minutes} min</td>
                                            <td className="py-2">
                                                <Badge variant={s.classification === 'unknown' ? 'danger' : 'success'}>
                                                    {STOP_CLASS_LABEL[s.classification ?? 'unknown'] ?? s.classification}
                                                </Badge>
                                            </td>
                                            <td className="py-2">{s.place?.name ?? '-'}</td>
                                            <td className="py-2 text-right">
                                                {s.fuel_delta_litres !== null
                                                    ? (
                                                        <span className={s.fuel_delta_litres < -10 ? 'text-red-500 font-semibold' : ''}>
                                                            {s.fuel_delta_litres > 0 ? '+' : ''}
                                                            {s.fuel_delta_litres.toFixed(1)} L
                                                        </span>
                                                    )
                                                    : '-'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </Card>
            )}

            {/* Incidents */}
            {data && data.incidents.length > 0 && (
                <Card header={`Incidents (${data.incidents.length})`}>
                    <div className="space-y-2">
                        {data.incidents.map((i) => (
                            <a
                                key={i.id}
                                href={`/logistics/theft-incidents/${i.id}`}
                                className="flex items-center gap-3 p-3 rounded-lg border border-[var(--color-border)] hover:bg-[var(--color-surface-hover)]"
                            >
                                <AlertTriangle size={16} className={i.severity === 'high' ? 'text-red-500' : 'text-amber-500'} />
                                <div className="flex-1">
                                    <div className="font-medium text-[var(--color-text)]">{i.title}</div>
                                    <div className="text-xs text-[var(--color-text-muted)]">{i.detected_at}</div>
                                </div>
                                <Badge variant={i.severity === 'high' ? 'danger' : 'warning'}>
                                    {i.severity}
                                </Badge>
                            </a>
                        ))}
                    </div>
                </Card>
            )}
        </AuthenticatedLayout>
    );
}
