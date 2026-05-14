import { Head, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import { AlertTriangle, Send, Truck as TruckIcon, CheckCircle2, Check } from 'lucide-react';
import { clsx } from 'clsx';

interface IssueEntry {
    id: number;
    category: string;
    severity: string | null;
    issue_notes: string | null;
    positions: string[];
    reported_at: string | null;
    resolved_at: string | null;
    resolution_notes: string | null;
}

interface Props {
    driver: { id: number; name: string };
    truck: { id: number; matricule: string; tire_count: number };
    recent: IssueEntry[];
    options: {
        severity: Record<string, string>;
        light_positions: Record<string, string>;
    };
}

const CATEGORIES: { key: string; label: string }[] = [
    { key: 'tires', label: 'Pneus' },
    { key: 'brakes', label: 'Freins' },
    { key: 'lights', label: 'Feux' },
    { key: 'oil', label: 'Huile' },
    { key: 'fuel', label: 'Carburant' },
    { key: 'general', label: 'Général' },
];

const CATEGORY_LABEL: Record<string, string> = Object.fromEntries(CATEGORIES.map((c) => [c.key, c.label]));

/* ── Tire diagram ── */
type Axle = { type: 'steering' | 'dual' | 'single'; tires: number[] };

function buildAxleLayout(tireCount: number): Axle[] {
    const axles: Axle[] = [];
    if (tireCount < 2) return axles;
    axles.push({ type: 'steering', tires: [1, 2] });
    let n = 3;
    let remaining = tireCount - 2;
    while (remaining > 0) {
        if (remaining >= 4) {
            axles.push({ type: 'dual', tires: [n, n + 1, n + 2, n + 3] });
            n += 4; remaining -= 4;
        } else if (remaining >= 2) {
            axles.push({ type: 'single', tires: [n, n + 1] });
            n += 2; remaining -= 2;
        } else { break; }
    }
    return axles;
}

function TireDiagram({ tireCount, selected, onToggle }: {
    tireCount: number;
    selected: string[];
    onToggle: (key: string) => void;
}) {
    const axles = buildAxleLayout(tireCount);
    const isSelected = (n: number) => selected.includes(`tire_${n}`);
    return (
        <div className="rounded-lg border border-dashed border-[var(--color-border)] p-3 bg-[var(--color-surface)]">
            <div className="text-center text-[10px] uppercase tracking-wide text-[var(--color-text-muted)] mb-1.5">Avant ↑</div>
            <div className="flex flex-col items-center gap-1.5">
                {(() => {
                    const tractorAxleCount = Math.min(3, axles.length);
                    let tCount = 0;
                    let rCount = 0;
                    return axles.map((axle, idx) => {
                        const left = axle.type === 'steering' ? [axle.tires[0]] : axle.tires.slice(0, axle.tires.length / 2);
                        const right = axle.type === 'steering' ? [axle.tires[1]] : axle.tires.slice(axle.tires.length / 2);
                        const axleLabel = idx < tractorAxleCount ? `T${++tCount}` : `R${++rCount}`;
                        return (
                            <div key={idx} className="flex items-center w-full justify-center gap-1">
                                <div className="flex items-center gap-1">
                                    {left.map((n) => (
                                        <button
                                            key={n}
                                            type="button"
                                            onClick={() => onToggle(`tire_${n}`)}
                                            className={clsx(
                                                'w-8 h-11 rounded text-[10px] font-bold border-2 transition shrink-0',
                                                isSelected(n)
                                                    ? 'border-red-500 bg-red-500 text-white'
                                                    : 'border-[var(--color-border)] bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)]'
                                            )}
                                        >{n}</button>
                                    ))}
                                </div>
                                <div className="flex-1 max-w-[160px] mx-2 flex items-center justify-center relative">
                                    <div className="h-1.5 w-full bg-[var(--color-border)] rounded-full" />
                                    <span className="absolute text-[10px] font-bold text-[var(--color-text-muted)] px-1.5 bg-[var(--color-surface)]">{axleLabel}</span>
                                </div>
                                <div className="flex items-center gap-1">
                                    {right.map((n) => (
                                        <button
                                            key={n}
                                            type="button"
                                            onClick={() => onToggle(`tire_${n}`)}
                                            className={clsx(
                                                'w-8 h-11 rounded text-[10px] font-bold border-2 transition shrink-0',
                                                isSelected(n)
                                                    ? 'border-red-500 bg-red-500 text-white'
                                                    : 'border-[var(--color-border)] bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)]'
                                            )}
                                        >{n}</button>
                                    ))}
                                </div>
                            </div>
                        );
                    });
                })()}
            </div>
            <div className="text-center text-[10px] uppercase tracking-wide text-[var(--color-text-muted)] mt-1.5">Arrière ↓</div>
        </div>
    );
}

function ChipsMultiSelect({ options, selected, onToggle }: {
    options: Record<string, string>;
    selected: string[];
    onToggle: (key: string) => void;
}) {
    return (
        <div className="flex flex-wrap gap-1.5">
            {Object.entries(options).map(([key, lbl]) => {
                const active = selected.includes(key);
                return (
                    <button
                        key={key}
                        type="button"
                        onClick={() => onToggle(key)}
                        className={clsx(
                            'px-2.5 py-1 rounded text-xs font-medium border transition',
                            active
                                ? 'border-red-500 bg-red-500/15 text-red-700 dark:text-red-300'
                                : 'border-[var(--color-border)] bg-[var(--color-surface)] text-[var(--color-text-secondary)]'
                        )}
                    >{lbl}</button>
                );
            })}
        </div>
    );
}

function SeverityPicker({ value, options, onChange }: {
    value: string;
    options: Record<string, string>;
    onChange: (v: string) => void;
}) {
    return (
        <div className="flex flex-wrap gap-1.5">
            {Object.entries(options).map(([key, lbl]) => {
                const active = value === key;
                const palette = key === 'critical'
                    ? { idle: 'border-red-300 text-red-700 dark:text-red-300', active: 'border-red-600 bg-red-500 text-white' }
                    : key === 'major'
                        ? { idle: 'border-amber-300 text-amber-700 dark:text-amber-300', active: 'border-amber-600 bg-amber-500 text-white' }
                        : { idle: 'border-blue-300 text-blue-700 dark:text-blue-300', active: 'border-blue-600 bg-blue-500 text-white' };
                return (
                    <button
                        key={key}
                        type="button"
                        onClick={() => onChange(active ? '' : key)}
                        className={clsx(
                            'px-3 py-1 rounded text-xs font-semibold border-2 transition',
                            active ? palette.active : `bg-[var(--color-surface)] ${palette.idle}`
                        )}
                    >{lbl}</button>
                );
            })}
        </div>
    );
}

function severityBadgeVariant(key: string | null): 'danger' | 'warning' | 'muted' {
    if (key === 'critical') return 'danger';
    if (key === 'major') return 'warning';
    return 'muted';
}

export default function Issues({ driver, truck, recent, options }: Props) {
    const form = useForm({
        flagged: [] as string[],
        severity: {} as Record<string, string>,
        positions: {} as Record<string, string[]>,
        notes: {} as Record<string, string>,
    });

    const isFlagged = (cat: string) => form.data.flagged.includes(cat);

    const toggleCategory = (cat: string) => {
        const next = isFlagged(cat)
            ? form.data.flagged.filter((c) => c !== cat)
            : [...form.data.flagged, cat];
        form.setData('flagged', next);
    };

    const setSeverity = (cat: string, value: string) => {
        form.setData('severity', { ...form.data.severity, [cat]: value });
    };

    const setNotes = (cat: string, value: string) => {
        form.setData('notes', { ...form.data.notes, [cat]: value });
    };

    const togglePosition = (cat: string, pos: string) => {
        const current = form.data.positions[cat] ?? [];
        const next = current.includes(pos) ? current.filter((p) => p !== pos) : [...current, pos];
        form.setData('positions', { ...form.data.positions, [cat]: next });
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/drivers/issues', {
            onSuccess: () => form.reset(),
        });
    };

    const formatPosition = (cat: string, key: string): string => {
        if (cat === 'tires') {
            const m = /^tire_(\d+)$/.exec(key);
            return m ? `Pneu ${m[1]}` : key;
        }
        if (cat === 'lights') return options.light_positions[key] ?? key;
        return key;
    };

    const flaggedCount = form.data.flagged.length;

    return (
        <AuthenticatedLayout title="Signaler un problème">
            <Head title="Signaler un problème" />

            <div className="rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-3 mb-4 flex items-center gap-3">
                <TruckIcon size={20} className="text-[var(--color-primary)]" />
                <div className="text-sm">
                    <div className="font-bold text-[var(--color-text)]">{truck.matricule}</div>
                    <div className="text-xs text-[var(--color-text-muted)]">Conducteur: {driver.name}</div>
                </div>
            </div>

            <Card className="mb-6">
                <form onSubmit={submit}>
                    <div className="flex items-center gap-2 mb-4">
                        <AlertTriangle size={18} className="text-amber-500" />
                        <h2 className="font-semibold text-[var(--color-text)]">Cochez tous les problèmes</h2>
                    </div>

                    <div className="space-y-2">
                        {CATEGORIES.map((cat) => {
                            const flagged = isFlagged(cat.key);
                            const severity = form.data.severity[cat.key] ?? '';
                            const positions = form.data.positions[cat.key] ?? [];
                            const notes = form.data.notes[cat.key] ?? '';
                            return (
                                <div
                                    key={cat.key}
                                    className={clsx(
                                        'rounded-lg border transition',
                                        flagged ? 'border-red-400 bg-red-500/5' : 'border-[var(--color-border)]'
                                    )}
                                >
                                    <button
                                        type="button"
                                        onClick={() => toggleCategory(cat.key)}
                                        className="flex items-center gap-3 w-full p-3 text-left"
                                    >
                                        <div className={clsx(
                                            'w-5 h-5 rounded border-2 flex items-center justify-center transition shrink-0',
                                            flagged ? 'border-red-500 bg-red-500 text-white' : 'border-[var(--color-border)]'
                                        )}>
                                            {flagged && <Check size={14} strokeWidth={3} />}
                                        </div>
                                        <span className="font-medium text-[var(--color-text)]">{cat.label}</span>
                                    </button>

                                    {flagged && (
                                        <div className="px-3 pb-3 space-y-3 border-t border-[var(--color-border)]/50 pt-3">
                                            <div>
                                                <p className="text-[11px] uppercase tracking-wide text-[var(--color-text-muted)] font-medium mb-1.5">Gravité</p>
                                                <SeverityPicker value={severity} options={options.severity} onChange={(v) => setSeverity(cat.key, v)} />
                                            </div>
                                            {cat.key === 'tires' && (
                                                <div>
                                                    <p className="text-[11px] uppercase tracking-wide text-[var(--color-text-muted)] font-medium mb-1.5">Pneu(s) concerné(s)</p>
                                                    <TireDiagram tireCount={truck.tire_count || 26} selected={positions} onToggle={(pos) => togglePosition(cat.key, pos)} />
                                                </div>
                                            )}
                                            {cat.key === 'lights' && (
                                                <div>
                                                    <p className="text-[11px] uppercase tracking-wide text-[var(--color-text-muted)] font-medium mb-1.5">Feu(x) concerné(s)</p>
                                                    <ChipsMultiSelect options={options.light_positions} selected={positions} onToggle={(pos) => togglePosition(cat.key, pos)} />
                                                </div>
                                            )}
                                            <input
                                                type="text"
                                                value={notes}
                                                onChange={(e) => setNotes(cat.key, e.target.value)}
                                                placeholder={`Détails du problème (${cat.label.toLowerCase()})...`}
                                                className="w-full px-3 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm"
                                                maxLength={500}
                                            />
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>

                    <Button type="submit" loading={form.processing} className="w-full sm:w-auto mt-5" disabled={flaggedCount === 0}>
                        <Send size={16} className="mr-2" />
                        {flaggedCount === 0 ? 'Sélectionnez au moins une catégorie' : `Signaler ${flaggedCount} problème${flaggedCount > 1 ? 's' : ''}`}
                    </Button>
                </form>
            </Card>

            <Card>
                <h2 className="font-semibold text-[var(--color-text)] mb-3">Historique ({recent.length})</h2>
                {recent.length === 0 ? (
                    <p className="text-sm text-[var(--color-text-muted)] py-4 text-center">Aucun signalement.</p>
                ) : (
                    <div className="space-y-2">
                        {recent.map((issue) => (
                            <div
                                key={issue.id}
                                className={clsx(
                                    'rounded-lg border p-3',
                                    issue.resolved_at
                                        ? 'border-emerald-300 bg-emerald-50/50 dark:bg-emerald-900/10'
                                        : 'border-[var(--color-border)]'
                                )}
                            >
                                <div className="flex flex-wrap items-center gap-2 mb-1">
                                    <Badge variant="danger">{CATEGORY_LABEL[issue.category] ?? issue.category}</Badge>
                                    {issue.severity && <Badge variant={severityBadgeVariant(issue.severity)}>{options.severity[issue.severity] ?? issue.severity}</Badge>}
                                    {issue.positions.map((p) => (
                                        <Badge key={p} variant="warning">{formatPosition(issue.category, p)}</Badge>
                                    ))}
                                    <span className="ml-auto text-xs text-[var(--color-text-muted)]">{issue.reported_at ?? '—'}</span>
                                </div>
                                {issue.issue_notes && <p className="text-sm text-[var(--color-text)]">{issue.issue_notes}</p>}
                                {issue.resolved_at && (
                                    <div className="mt-2 flex items-start gap-2 text-xs text-emerald-700 dark:text-emerald-300">
                                        <CheckCircle2 size={14} className="mt-0.5 shrink-0" />
                                        <div>
                                            Résolu le {issue.resolved_at}
                                            {issue.resolution_notes && <span> — {issue.resolution_notes}</span>}
                                        </div>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </Card>
        </AuthenticatedLayout>
    );
}
