import { Clock, Fuel, Route, Scale, Wifi, AlertCircle, FileCheck, FileX, MapPin } from 'lucide-react';
import Badge from '@/components/ui/Badge';
import { formatMinutes } from '@/utils/theft-incident';

type EvidenceRecord = Record<string, any>;

interface Props {
    type: string;
    evidence: EvidenceRecord | null;
}

function formatLitres(v: number | null | undefined): string {
    if (v == null || !Number.isFinite(Number(v))) return '—';
    return `${Number(v).toFixed(1)} L`;
}

function formatKm(v: number | null | undefined): string {
    if (v == null || !Number.isFinite(Number(v))) return '—';
    return `${Number(v).toLocaleString('fr-FR', { maximumFractionDigits: 2 })} km`;
}

function formatKg(v: number | null | undefined): string {
    if (v == null || !Number.isFinite(Number(v))) return '—';
    return `${Number(v).toLocaleString('fr-FR', { maximumFractionDigits: 1 })} kg`;
}

function formatDate(iso: string | null | undefined): string {
    if (!iso) return '—';
    try {
        const d = new Date(iso);
        return d.toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' });
    } catch {
        return iso;
    }
}

function Row({ label, value, hint }: { label: string; value: React.ReactNode; hint?: string }) {
    return (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-2 py-1.5 border-b border-[var(--color-border)] last:border-0 text-sm">
            <div className="text-[var(--color-text-muted)] md:col-span-1">{label}</div>
            <div className="font-medium md:col-span-2">{value}{hint && <span className="text-xs text-[var(--color-text-muted)] ml-2">{hint}</span>}</div>
        </div>
    );
}

function Section({ icon, title, children }: { icon: React.ReactNode; title: string; children: React.ReactNode }) {
    return (
        <div>
            <h3 className="text-sm font-semibold flex items-center gap-2 mb-2 text-[var(--color-text)]">{icon}{title}</h3>
            <div className="rounded-lg border border-[var(--color-border)] px-3 py-1">
                {children}
            </div>
        </div>
    );
}

export default function EvidencePanel({ type, evidence }: Props) {
    if (!evidence) {
        return <p className="text-sm text-[var(--color-text-muted)]">Pas de données d'évidence.</p>;
    }

    if (type === 'unauthorized_stop') {
        const fuelDelta = Number(evidence.fuel_delta_litres ?? 0);
        const fuelLoss = fuelDelta < 0 ? Math.abs(fuelDelta) : 0;
        return (
            <Section icon={<Clock size={16} className="text-amber-500" />} title="Arrêt non autorisé">
                <Row label="Durée de l'arrêt" value={<span className="font-semibold">{formatMinutes(evidence.duration_minutes)}</span>} />
                <Row label="Début" value={formatDate(evidence.started_at)} />
                <Row label="Fin" value={formatDate(evidence.ended_at)} />
                <Row label="Moteur" value={evidence.ignition_was_off ? <Badge variant="muted">Arrêté</Badge> : <Badge variant="warning">En marche</Badge>} />
                <Row label="Carburant au début" value={formatLitres(evidence.fuel_litres_at_start)} />
                <Row label="Carburant à la fin" value={formatLitres(evidence.fuel_litres_at_end)} />
                {fuelLoss > 0 && (
                    <Row label="Perte de carburant" value={<span className="text-[var(--color-danger)] font-semibold">{formatLitres(fuelLoss)}</span>} hint="potentiel siphonnage" />
                )}
            </Section>
        );
    }

    if (type === 'route_deviation') {
        return (
            <Section icon={<Route size={16} className="text-blue-500" />} title="Déviation d'itinéraire">
                <Row label="Origine" value={evidence.origin_place?.name ?? '—'} />
                <Row label="Destination" value={evidence.destination_place?.name ?? '—'} />
                <Row label="Distance ligne droite" value={formatKm(evidence.straight_line_km)} />
                <Row label="Distance maximale attendue" value={formatKm(evidence.max_expected_km)} hint="ligne droite × tolérance" />
                <Row label="Distance réelle parcourue" value={<span className="font-semibold">{formatKm(evidence.actual_km)}</span>} />
                <Row label="Excès de distance" value={<span className="text-[var(--color-danger)] font-semibold">{formatKm(evidence.excess_km)}</span>} />
                {evidence.factor != null && (
                    <Row label="Facteur" value={`× ${Number(evidence.factor).toFixed(2)}`} hint="trajet / ligne droite" />
                )}
            </Section>
        );
    }

    if (type === 'fuel_siphoning') {
        return (
            <Section icon={<Fuel size={16} className="text-red-500" />} title="Siphonnage de carburant suspecté">
                <Row label="Carburant avant" value={formatLitres(evidence.litres_before)} />
                <Row label="Carburant après" value={formatLitres(evidence.litres_after)} />
                <Row label="Variation" value={<span className="text-[var(--color-danger)] font-semibold">{formatLitres(evidence.litres_delta)}</span>} hint="négatif = chute" />
                <Row label="Moteur" value={evidence.ignition_on ? <Badge variant="warning">En marche</Badge> : <Badge variant="muted">Arrêté</Badge>} />
            </Section>
        );
    }

    if (type === 'off_hours_movement') {
        return (
            <Section icon={<Wifi size={16} className="text-purple-500" />} title="Mouvement hors heures">
                <Row label="Début de la fenêtre" value={formatDate(evidence.window_start)} />
                <Row label="Fin de la fenêtre" value={formatDate(evidence.window_end)} />
                <Row label="Vitesse maximale" value={`${Number(evidence.max_speed_kmh ?? 0).toFixed(0)} km/h`} />
                <Row label="Nombre de points GPS" value={evidence.snapshot_count ?? '—'} />
            </Section>
        );
    }

    if (type === 'untracked_trip') {
        const ticketLinked = Boolean(evidence.linked_transport_tracking_id);
        const parking = evidence.parking_place ?? null;
        const provider = evidence.provider_place ?? null;
        const client = evidence.client_place ?? null;
        return (
            <Section icon={<Route size={16} className="text-red-500" />} title="Voyage sans bon de transport">
                <Row
                    label="Bon de transport"
                    value={ticketLinked
                        ? <span className="inline-flex items-center gap-1 text-emerald-600"><FileCheck size={14} /> Lié</span>
                        : <span className="inline-flex items-center gap-1 text-[var(--color-danger)] font-semibold"><FileX size={14} /> Aucun bon enregistré</span>}
                />
                <Row label="Camion" value={evidence.truck_matricule ?? '—'} />
                <Row label="Distance totale" value={formatKm(evidence.distance_km)} />
                <div className="py-3">
                    <div className="text-[var(--color-text-muted)] text-xs uppercase mb-2 flex items-center gap-1">
                        <MapPin size={12} /> Chronologie GPS
                    </div>
                    <ol className="space-y-2 text-sm">
                        <li className="flex justify-between gap-3">
                            <span><span className="text-[var(--color-text-muted)]">Départ parking</span> — <span className="font-medium">{parking?.label ?? '—'}</span></span>
                            <span className="font-mono text-xs">{formatDate(evidence.parking_departure_at)}</span>
                        </li>
                        <li className="flex justify-between gap-3">
                            <span><span className="text-[var(--color-text-muted)]">Arrivée carrière</span> — <span className="font-medium">{provider?.label ?? '—'}</span></span>
                            <span className="font-mono text-xs">{formatDate(evidence.provider_arrival_at)}</span>
                        </li>
                        <li className="flex justify-between gap-3">
                            <span><span className="text-[var(--color-text-muted)]">Départ carrière</span></span>
                            <span className="font-mono text-xs">{formatDate(evidence.provider_departure_at)}</span>
                        </li>
                        <li className="flex justify-between gap-3">
                            <span><span className="text-[var(--color-text-muted)]">Arrivée chantier</span> — <span className="font-medium">{client?.label ?? '—'}</span></span>
                            <span className="font-mono text-xs">{formatDate(evidence.client_arrival_at)}</span>
                        </li>
                        <li className="flex justify-between gap-3">
                            <span><span className="text-[var(--color-text-muted)]">Départ chantier</span></span>
                            <span className="font-mono text-xs">{formatDate(evidence.client_departure_at)}</span>
                        </li>
                        <li className="flex justify-between gap-3">
                            <span><span className="text-[var(--color-text-muted)]">Retour parking</span></span>
                            <span className="font-mono text-xs">{formatDate(evidence.parking_arrival_at)}</span>
                        </li>
                    </ol>
                </div>
            </Section>
        );
    }

    if (type === 'weight_gap') {
        const unknownStops = Array.isArray(evidence.unknown_stops) ? evidence.unknown_stops : [];
        return (
            <Section icon={<Scale size={16} className="text-orange-500" />} title="Écart de poids">
                <Row label="Poids chargé (carrière)" value={formatKg((Number(evidence.provider_net_weight ?? 0)) * 1000)} />
                <Row label="Poids livré (chantier)" value={formatKg((Number(evidence.client_net_weight ?? 0)) * 1000)} />
                <Row label="Écart" value={<span className="font-semibold">{formatKg(evidence.gap_kg)}</span>} />
                {evidence.loss_kg != null && Number(evidence.loss_kg) > 0 && (
                    <Row label="Perte" value={<span className="text-[var(--color-danger)] font-semibold">{formatKg(evidence.loss_kg)}</span>} />
                )}
                {unknownStops.length > 0 && (
                    <div className="py-2">
                        <div className="text-[var(--color-text-muted)] text-sm mb-1">Arrêts non identifiés pendant le trajet ({unknownStops.length})</div>
                        <ul className="text-xs space-y-1">
                            {unknownStops.slice(0, 5).map((s: any) => (
                                <li key={s.id} className="border-l-2 border-[var(--color-border)] pl-2">
                                    {formatDate(s.started_at)} — durée {formatMinutes(s.duration_minutes)}
                                </li>
                            ))}
                            {unknownStops.length > 5 && (
                                <li className="text-[var(--color-text-muted)]">… et {unknownStops.length - 5} autres</li>
                            )}
                        </ul>
                    </div>
                )}
            </Section>
        );
    }

    // Fallback: type inconnu → afficher un rendu clé/valeur générique mais lisible
    return (
        <Section icon={<AlertCircle size={16} className="text-slate-500" />} title="Évidence">
            {Object.entries(evidence)
                .filter(([k]) => k !== 'dedup_key')
                .map(([k, v]) => (
                    <Row key={k} label={k} value={typeof v === 'object' ? <code className="text-xs">{JSON.stringify(v)}</code> : String(v ?? '—')} />
                ))}
        </Section>
    );
}
