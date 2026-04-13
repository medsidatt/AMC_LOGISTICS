import { Head, router, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import LeafletMap from '@/components/map/LeafletMap';
import { ArrowLeft, CheckCircle, XCircle, AlertTriangle } from 'lucide-react';

interface Incident {
    id: number;
    type: string;
    severity: string;
    status: string;
    title: string;
    detected_at: string | null;
    latitude: number | null;
    longitude: number | null;
    evidence: Record<string, any> | null;
    review_notes: string | null;
    reviewed_at: string | null;
    reviewer: string | null;
    truck: { id: number; matricule: string } | null;
    transport_tracking: {
        id: number;
        reference: string;
        provider_date: string | null;
        client_date: string | null;
        provider_net_weight: number | null;
        client_net_weight: number | null;
        gap: number | null;
    } | null;
    trip_segment_id: number | null;
    truck_stop_id: number | null;
    fuel_event_id: number | null;
}

interface Props {
    incident: Incident;
}

const TYPE_LABELS: Record<string, string> = {
    fuel_siphoning: 'Vol de carburant',
    weight_gap: 'Écart de poids',
    unauthorized_stop: 'Arrêt non autorisé',
    route_deviation: "Déviation d'itinéraire",
    off_hours_movement: 'Mouvement hors horaires',
};

const SEVERITY_COLOR: Record<string, string> = {
    high: '#ef4444',
    medium: '#f59e0b',
    low: '#3b82f6',
};

export default function TheftIncidentShow({ incident }: Props) {
    const form = useForm<{ action: 'review' | 'dismiss' | 'confirm'; notes: string }>({
        action: 'review',
        notes: '',
    });

    const handleAction = (action: 'review' | 'dismiss' | 'confirm') => {
        form.setData('action', action);
        form.post(`/logistics/theft-incidents/${incident.id}/status`);
    };

    const severityBadge = (severity: string) => {
        const variant = severity === 'high' ? 'danger' : severity === 'medium' ? 'warning' : 'info';
        return <Badge variant={variant}>{severity === 'high' ? 'Haute' : severity === 'medium' ? 'Moyenne' : 'Basse'}</Badge>;
    };

    const statusBadge = (status: string) => {
        const variant =
            status === 'pending' ? 'warning' :
            status === 'confirmed' ? 'danger' :
            status === 'dismissed' ? 'muted' :
            'info';
        const label =
            status === 'pending' ? 'En attente' :
            status === 'confirmed' ? 'Confirmé' :
            status === 'dismissed' ? 'Rejeté' :
            'Examiné';
        return <Badge variant={variant}>{label}</Badge>;
    };

    const hasLocation = incident.latitude !== null && incident.longitude !== null;

    return (
        <AuthenticatedLayout title={`Incident #${incident.id}`}>
            <Head title={`Incident #${incident.id}`} />

            <div className="mb-4">
                <Button variant="ghost" onClick={() => router.visit('/logistics/theft-incidents')} icon={<ArrowLeft size={14} />}>
                    Retour aux incidents
                </Button>
            </div>

            <Card className="mb-5">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-bold text-[var(--color-text)] mb-2">
                            {incident.title}
                        </h2>
                        <div className="flex flex-wrap gap-2 items-center">
                            <span className="text-sm text-[var(--color-text-muted)]">
                                {TYPE_LABELS[incident.type] ?? incident.type}
                            </span>
                            {severityBadge(incident.severity)}
                            {statusBadge(incident.status)}
                            <span className="text-sm text-[var(--color-text-muted)]">
                                Détecté: {incident.detected_at ?? '-'}
                            </span>
                        </div>
                    </div>
                </div>
            </Card>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
                <Card header="Camion" className="lg:col-span-1">
                    {incident.truck ? (
                        <div className="space-y-2">
                            <div className="flex justify-between">
                                <span className="text-xs text-[var(--color-text-muted)] uppercase">Matricule</span>
                                <a
                                    href={`/trucks/${incident.truck.id}/show-page`}
                                    className="text-sm font-semibold text-[var(--color-primary)] hover:underline"
                                >
                                    {incident.truck.matricule}
                                </a>
                            </div>
                        </div>
                    ) : (
                        <p className="text-sm text-[var(--color-text-muted)]">Aucun camion lié.</p>
                    )}
                </Card>

                <Card header="Mission de transport" className="lg:col-span-2">
                    {incident.transport_tracking ? (
                        <div className="space-y-2 text-sm">
                            <div className="flex justify-between">
                                <span className="text-[var(--color-text-muted)]">Référence</span>
                                <a
                                    href={`/transport_tracking/${incident.transport_tracking.id}/show-page`}
                                    className="font-semibold text-[var(--color-primary)] hover:underline"
                                >
                                    {incident.transport_tracking.reference}
                                </a>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-[var(--color-text-muted)]">Date fournisseur</span>
                                <span>{incident.transport_tracking.provider_date ?? '-'}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-[var(--color-text-muted)]">Date client</span>
                                <span>{incident.transport_tracking.client_date ?? '-'}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-[var(--color-text-muted)]">Poids fournisseur</span>
                                <span>{incident.transport_tracking.provider_net_weight ?? '-'} kg</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-[var(--color-text-muted)]">Poids client</span>
                                <span>{incident.transport_tracking.client_net_weight ?? '-'} kg</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-[var(--color-text-muted)]">Écart</span>
                                <span className={incident.transport_tracking.gap && incident.transport_tracking.gap < 0 ? 'text-red-500 font-semibold' : ''}>
                                    {incident.transport_tracking.gap ?? '-'} kg
                                </span>
                            </div>
                            {incident.trip_segment_id && (
                                <div className="pt-2">
                                    <a
                                        href={`/transport_tracking/${incident.transport_tracking.id}/replay`}
                                        className="inline-flex items-center gap-1 text-sm text-[var(--color-primary)] hover:underline"
                                    >
                                        Voir la reprise du trajet →
                                    </a>
                                </div>
                            )}
                        </div>
                    ) : (
                        <p className="text-sm text-[var(--color-text-muted)]">Aucune mission liée.</p>
                    )}
                </Card>
            </div>

            {hasLocation && (
                <Card header="Localisation" className="mb-5" padding={false}>
                    <div className="p-5">
                        <LeafletMap
                            height={360}
                            markers={[
                                {
                                    id: incident.id,
                                    latitude: incident.latitude!,
                                    longitude: incident.longitude!,
                                    color: SEVERITY_COLOR[incident.severity] ?? '#3b82f6',
                                    popup: (
                                        <div>
                                            <strong>{incident.title}</strong>
                                            <br />
                                            <span style={{ fontSize: 12 }}>{incident.detected_at}</span>
                                        </div>
                                    ),
                                },
                            ]}
                        />
                    </div>
                </Card>
            )}

            <Card header="Évidence détaillée" className="mb-5">
                {incident.evidence ? (
                    <pre className="text-xs font-mono bg-[var(--color-surface-hover)] p-4 rounded-lg overflow-x-auto text-[var(--color-text)]">
                        {JSON.stringify(incident.evidence, null, 2)}
                    </pre>
                ) : (
                    <p className="text-sm text-[var(--color-text-muted)]">Pas de données d'évidence.</p>
                )}
            </Card>

            {incident.status !== 'pending' && (
                <Card header="Historique d'examen" className="mb-5">
                    <div className="space-y-2 text-sm">
                        <div className="flex justify-between">
                            <span className="text-[var(--color-text-muted)]">Examiné par</span>
                            <span>{incident.reviewer ?? '-'}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-[var(--color-text-muted)]">Examiné le</span>
                            <span>{incident.reviewed_at ?? '-'}</span>
                        </div>
                        {incident.review_notes && (
                            <div className="pt-2">
                                <span className="text-[var(--color-text-muted)] block mb-1">Notes</span>
                                <p className="text-[var(--color-text)]">{incident.review_notes}</p>
                            </div>
                        )}
                    </div>
                </Card>
            )}

            {incident.status === 'pending' && (
                <Card header="Actions">
                    <textarea
                        value={form.data.notes}
                        onChange={(e) => form.setData('notes', e.target.value)}
                        placeholder="Notes d'examen (optionnel)..."
                        rows={3}
                        className="w-full px-3 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm text-[var(--color-text)] mb-4"
                    />
                    <div className="flex flex-wrap gap-2">
                        <Button
                            onClick={() => handleAction('confirm')}
                            loading={form.processing}
                            icon={<AlertTriangle size={16} />}
                            variant="danger"
                        >
                            Confirmer le vol
                        </Button>
                        <Button
                            onClick={() => handleAction('review')}
                            loading={form.processing}
                            icon={<CheckCircle size={16} />}
                        >
                            Marquer comme examiné
                        </Button>
                        <Button
                            onClick={() => handleAction('dismiss')}
                            loading={form.processing}
                            icon={<XCircle size={16} />}
                            variant="secondary"
                        >
                            Rejeter (faux positif)
                        </Button>
                    </div>
                </Card>
            )}
        </AuthenticatedLayout>
    );
}
