import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';

export interface ReconciliationRow {
    id: number;
    status: 'expected' | 'matched' | 'missing' | 'dismissed';
    loaded_at: string | null;
    left_at: string | null;
    deadline_at: string | null;
    provider: { id: number; name: string } | null;
    truck: { id: number; matricule: string } | null;
    driver: { id: number; name: string } | null;
    dispatch_date: string | null;
    tracking: { id: number; reference: string } | null;
}

interface Props {
    rows: ReconciliationRow[];
    counts: { expected: number; missing: number; matched: number };
}

function formatDateTime(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' });
}

function statusBadge(status: ReconciliationRow['status']) {
    switch (status) {
        case 'matched': return <Badge variant="success">Apparié</Badge>;
        case 'missing': return <Badge variant="danger">Manquant</Badge>;
        case 'expected': return <Badge variant="warning">Attendu</Badge>;
        default: return <Badge variant="muted">Ignoré</Badge>;
    }
}

/**
 * Reconciliation worklist (GPS-observed quarry loads vs registered tickets).
 * Presentational only — shared by the standalone /reports/ticket-gap page and the
 * Operations workspace Reconciliation tab. No data fetching / business logic here.
 */
export default function ReconciliationPanel({ rows, counts }: Props) {
    return (
        <div className="space-y-4">
            <div className="grid grid-cols-3 gap-3">
                <StatCard label="Manquants" value={counts.missing} color="text-red-500" />
                <StatCard label="Attendus" value={counts.expected} color="text-amber-500" />
                <StatCard label="Appariés (90j)" value={counts.matched} color="text-emerald-500" />
            </div>

            <Card padding={false}>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="bg-[var(--color-surface-hover)] text-xs uppercase text-[var(--color-text-muted)]">
                            <tr>
                                <th className="text-left px-3 py-2">Date programme</th>
                                <th className="text-left px-3 py-2">Camion</th>
                                <th className="text-left px-3 py-2">Conducteur</th>
                                <th className="text-left px-3 py-2">Carrière</th>
                                <th className="text-left px-3 py-2">Chargé à</th>
                                <th className="text-left px-3 py-2">Échéance</th>
                                <th className="text-left px-3 py-2">Statut</th>
                                <th className="text-left px-3 py-2">Ticket</th>
                                <th className="text-right px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 ? (
                                <tr><td colSpan={9} className="px-3 py-8 text-center text-[var(--color-text-muted)]">
                                    Aucun écart détecté.
                                </td></tr>
                            ) : rows.map((r) => (
                                <tr key={r.id} className="border-t border-[var(--color-border)]">
                                    <td className="px-3 py-2 whitespace-nowrap">{r.dispatch_date ?? '—'}</td>
                                    <td className="px-3 py-2 font-medium">{r.truck?.matricule ?? '—'}</td>
                                    <td className="px-3 py-2">{r.driver?.name ?? '—'}</td>
                                    <td className="px-3 py-2">{r.provider?.name ?? '—'}</td>
                                    <td className="px-3 py-2 whitespace-nowrap">{formatDateTime(r.loaded_at)}</td>
                                    <td className="px-3 py-2 whitespace-nowrap">{formatDateTime(r.deadline_at)}</td>
                                    <td className="px-3 py-2">{statusBadge(r.status)}</td>
                                    <td className="px-3 py-2">
                                        {r.tracking ? (
                                            <a href={`/transport_tracking/${r.tracking.id}/show-page`} className="text-[var(--color-primary)] hover:underline">
                                                {r.tracking.reference}
                                            </a>
                                        ) : '—'}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        {r.status === 'missing' && r.truck && r.provider && (
                                            <a
                                                href={`/transport_tracking/create-page?truck_id=${r.truck.id}&provider_id=${r.provider.id}&provider_date=${r.loaded_at?.slice(0, 10) ?? ''}`}
                                                className="text-[var(--color-primary)] hover:underline text-xs"
                                            >
                                                Créer ticket
                                            </a>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Card>
        </div>
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
