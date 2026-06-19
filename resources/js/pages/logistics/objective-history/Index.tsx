import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import { ArrowDownRight, ArrowUpRight, Minus, History } from 'lucide-react';

interface Entry {
    id: number;
    subject_type: string | null;
    subject_label: string | null;
    field_name: string;
    field_label: string | null;
    old_value: string | null;
    new_value: string | null;
    magnitude: number | null;
    direction: 'increase' | 'decrease' | 'neutral';
    note: string;
    context: Record<string, unknown> | null;
    user: { id: number; name: string } | null;
    changed_at: string | null;
}

interface Filters {
    field: string | null;
    direction: string | null;
    subject_type: string | null;
    from: string | null;
    to: string | null;
}

interface Props {
    entries: Entry[];
    filters: Filters;
}

const FIELD_OPTIONS: { value: string; label: string }[] = [
    { value: '', label: 'Tous les champs' },
    { value: 'target_rotations_per_week', label: 'Rotations/semaine cible' },
    { value: 'default_capacity_tonnage', label: 'Capacité par défaut' },
    { value: 'capacity_tonnage', label: 'Capacité camion' },
    { value: 'monthly_target_tonnage', label: 'Objectif mensuel' },
    { value: 'required_tons', label: 'Tonnage demandé' },
    { value: 'required_trucks', label: 'Camions demandés' },
];

const DIRECTION_OPTIONS: { value: string; label: string }[] = [
    { value: '', label: 'Toutes directions' },
    { value: 'increase', label: 'Hausse' },
    { value: 'decrease', label: 'Baisse' },
    { value: 'neutral', label: 'Neutre' },
];

function DirectionBadge({ direction, magnitude }: { direction: Entry['direction']; magnitude: number | null }) {
    if (direction === 'increase') {
        return (
            <Badge variant="success" className="inline-flex items-center gap-1">
                <ArrowUpRight size={12} />
                {magnitude !== null ? `+${magnitude}` : 'Hausse'}
            </Badge>
        );
    }
    if (direction === 'decrease') {
        return (
            <Badge variant="danger" className="inline-flex items-center gap-1">
                <ArrowDownRight size={12} />
                {magnitude !== null ? `−${magnitude}` : 'Baisse'}
            </Badge>
        );
    }
    return (
        <Badge variant="muted" className="inline-flex items-center gap-1">
            <Minus size={12} />
            Neutre
        </Badge>
    );
}

export default function ObjectiveHistoryIndex({ entries, filters }: Props) {
    const [field, setField] = useState(filters.field ?? '');
    const [direction, setDirection] = useState(filters.direction ?? '');
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');

    const apply = () => {
        router.get('/logistics/objective-history', {
            field: field || undefined,
            direction: direction || undefined,
            from: from || undefined,
            to: to || undefined,
        }, { preserveScroll: true, preserveState: true });
    };

    const reset = () => {
        setField(''); setDirection(''); setFrom(''); setTo('');
        router.get('/logistics/objective-history', {}, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout title="Journal des objectifs">
            <Head title="Journal des objectifs" />

            <Card
                className="mb-4"
                header={
                    <div className="flex items-center gap-2">
                        <History size={16} />
                        <span className="text-sm font-semibold">Filtres</span>
                    </div>
                }
            >
                <div className="grid grid-cols-1 md:grid-cols-5 gap-3">
                    <div>
                        <label className="text-xs text-[var(--color-text-muted)] block mb-1">Champ</label>
                        <select
                            className="w-full px-2 py-1.5 text-sm rounded border border-[var(--color-border)] bg-[var(--color-surface)]"
                            value={field}
                            onChange={(e) => setField(e.target.value)}
                        >
                            {FIELD_OPTIONS.map((o) => (
                                <option key={o.value} value={o.value}>{o.label}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className="text-xs text-[var(--color-text-muted)] block mb-1">Direction</label>
                        <select
                            className="w-full px-2 py-1.5 text-sm rounded border border-[var(--color-border)] bg-[var(--color-surface)]"
                            value={direction}
                            onChange={(e) => setDirection(e.target.value)}
                        >
                            {DIRECTION_OPTIONS.map((o) => (
                                <option key={o.value} value={o.value}>{o.label}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className="text-xs text-[var(--color-text-muted)] block mb-1">Du</label>
                        <input
                            type="date"
                            className="w-full px-2 py-1.5 text-sm rounded border border-[var(--color-border)] bg-[var(--color-surface)]"
                            value={from}
                            onChange={(e) => setFrom(e.target.value)}
                        />
                    </div>
                    <div>
                        <label className="text-xs text-[var(--color-text-muted)] block mb-1">Au</label>
                        <input
                            type="date"
                            className="w-full px-2 py-1.5 text-sm rounded border border-[var(--color-border)] bg-[var(--color-surface)]"
                            value={to}
                            onChange={(e) => setTo(e.target.value)}
                        />
                    </div>
                    <div className="flex items-end gap-2">
                        <Button onClick={apply}>Filtrer</Button>
                        <Button variant="secondary" onClick={reset}>Réinitialiser</Button>
                    </div>
                </div>
            </Card>

            <Card>
                {entries.length === 0 ? (
                    <div className="text-sm text-[var(--color-text-muted)] py-8 text-center">
                        Aucun changement d'objectif enregistré pour ces filtres.
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm min-w-[900px]">
                            <thead>
                                <tr className="text-xs text-[var(--color-text-muted)] uppercase border-b border-[var(--color-border)]">
                                    <th className="text-left py-2 px-2">Date</th>
                                    <th className="text-left py-2 px-2">Sujet</th>
                                    <th className="text-left py-2 px-2">Champ</th>
                                    <th className="text-left py-2 px-2">Avant → Après</th>
                                    <th className="text-left py-2 px-2">Direction</th>
                                    <th className="text-left py-2 px-2">Auteur</th>
                                    <th className="text-left py-2 px-2">Justification</th>
                                </tr>
                            </thead>
                            <tbody>
                                {entries.map((e) => (
                                    <tr key={e.id} className="border-b border-[var(--color-border)] last:border-0 align-top">
                                        <td className="py-2 px-2 whitespace-nowrap text-[var(--color-text-muted)]">{e.changed_at}</td>
                                        <td className="py-2 px-2">
                                            <div className="font-medium">{e.subject_label ?? '—'}</div>
                                        </td>
                                        <td className="py-2 px-2">{e.field_label ?? e.field_name}</td>
                                        <td className="py-2 px-2 whitespace-nowrap">
                                            <span className="text-[var(--color-text-muted)]">{e.old_value ?? '∅'}</span>
                                            {' → '}
                                            <span className="font-semibold">{e.new_value ?? '∅'}</span>
                                        </td>
                                        <td className="py-2 px-2">
                                            <DirectionBadge direction={e.direction} magnitude={e.magnitude} />
                                        </td>
                                        <td className="py-2 px-2">{e.user?.name ?? '—'}</td>
                                        <td className="py-2 px-2 max-w-md">
                                            <div className="text-[var(--color-text)] whitespace-pre-wrap">{e.note}</div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                {entries.length >= 500 && (
                    <p className="text-xs text-[var(--color-text-muted)] mt-3">
                        Affichage limité à 500 entrées les plus récentes — affinez les filtres pour voir l'historique antérieur.
                    </p>
                )}
            </Card>
        </AuthenticatedLayout>
    );
}
