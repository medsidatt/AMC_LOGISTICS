import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import Modal from '@/components/ui/Modal';
import CameraCapture from '@/components/inspection/CameraCapture';
import { AlertTriangle, Send, Truck as TruckIcon, CheckCircle2, Check, Receipt, FileText, Wallet, Camera } from 'lucide-react';
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
    parts_cost: string | null;
    labor_cost: string | null;
    total_cost: string | null;
    devis_url: string | null;
    devis_name: string | null;
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
    { key: 'general', label: 'Général' },
];

const CATEGORY_LABEL: Record<string, string> = Object.fromEntries(CATEGORIES.map((c) => [c.key, c.label]));

const DEFAULT_SEVERITY = 'minor';

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
    const form = useForm<Record<string, any>>({
        flagged: [] as string[],
        severity: {} as Record<string, string>,
        positions: {} as Record<string, string[]>,
        notes: {} as Record<string, string>,
        parts_cost: {} as Record<string, string>,
        labor_cost: {} as Record<string, string>,
        attachments: {} as Record<string, File | null>,
    });

    const [costIssue, setCostIssue] = useState<IssueEntry | null>(null);
    const costForm = useForm<Record<string, any>>({
        parts_cost: '',
        labor_cost: '',
        devis: null as File | null,
    });

    const fcfa = (v: string | null) =>
        v == null || v === '' ? null : `${Number(v).toLocaleString('fr-FR')} FCFA`;

    const openCost = (issue: IssueEntry) => {
        setCostIssue(issue);
        costForm.setData({
            parts_cost: issue.parts_cost ?? '',
            labor_cost: issue.labor_cost ?? '',
            devis: null,
        });
        costForm.clearErrors();
    };

    const costTotal = () => (Number(costForm.data.parts_cost) || 0) + (Number(costForm.data.labor_cost) || 0);

    const submitCost = (e: React.FormEvent) => {
        e.preventDefault();
        if (!costIssue) return;
        costForm.post(`/drivers/issues/${costIssue.id}/cost`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => setCostIssue(null),
        });
    };

    const isFlagged = (cat: string) => form.data.flagged.includes(cat);

    const [severityErrors, setSeverityErrors] = useState<Record<string, boolean>>({});

    const toggleCategory = (cat: string) => {
        const turningOn = !isFlagged(cat);
        const next = turningOn
            ? [...form.data.flagged, cat]
            : form.data.flagged.filter((c) => c !== cat);
        form.setData('flagged', next);

        // Default the status to "minor" when a category is checked so a status is
        // always selected; require an explicit choice but pre-fill the lowest level.
        if (turningOn && !form.data.severity[cat]) {
            form.setData('severity', { ...form.data.severity, [cat]: DEFAULT_SEVERITY });
        }
    };

    const setSeverity = (cat: string, value: string) => {
        form.setData('severity', { ...form.data.severity, [cat]: value });
        setSeverityErrors((prev) => ({ ...prev, [cat]: false }));
    };

    const setNotes = (cat: string, value: string) => {
        form.setData('notes', { ...form.data.notes, [cat]: value });
    };

    const setCost = (cat: string, field: 'parts_cost' | 'labor_cost', value: string) => {
        form.setData(field, { ...form.data[field], [cat]: value });
    };

    const setAttachment = (cat: string, file: File | null) => {
        form.setData('attachments', { ...form.data.attachments, [cat]: file });
    };

    const catTotal = (cat: string) =>
        (Number(form.data.parts_cost[cat]) || 0) + (Number(form.data.labor_cost[cat]) || 0);

    const togglePosition = (cat: string, pos: string) => {
        const current = form.data.positions[cat] ?? [];
        const next = current.includes(pos) ? current.filter((p) => p !== pos) : [...current, pos];
        form.setData('positions', { ...form.data.positions, [cat]: next });
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        // A status is required for every checked category.
        const missing: Record<string, boolean> = {};
        for (const cat of form.data.flagged) {
            if (!form.data.severity[cat]) missing[cat] = true;
        }
        if (Object.keys(missing).length > 0) {
            setSeverityErrors(missing);
            return;
        }

        form.post('/drivers/issues', {
            forceFormData: true,
            onSuccess: () => {
                form.reset();
                setSeverityErrors({});
            },
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

    // The form is submittable only when at least one category is checked and every
    // checked category has a status and its required item(s) selected (pneus/feux).
    const requiresPositions = (cat: string) => cat === 'tires' || cat === 'lights';
    const formValid =
        flaggedCount > 0 &&
        form.data.flagged.every((cat) => {
            if (!form.data.severity[cat]) return false;
            if (requiresPositions(cat) && (form.data.positions[cat]?.length ?? 0) === 0) return false;
            if (catTotal(cat) <= 0) return false;
            return true;
        });

    return (
        <AuthenticatedLayout title="Signaler un problème">
            <Head title="Signaler un problème" />

            <Card className="mb-4">
                <div className="flex flex-wrap items-center gap-3">
                    <div className="p-2 rounded-xl bg-[var(--color-primary)]/10 shrink-0">
                        <TruckIcon size={20} className="text-[var(--color-primary)]" />
                    </div>
                    <div className="text-sm flex-1 min-w-0">
                        <div className="font-bold truncate">{truck.matricule}</div>
                        <div className="text-xs text-[var(--color-text-muted)]">Conducteur : {driver.name}</div>
                    </div>
                    {flaggedCount > 0 && (
                        <Badge variant="warning">{flaggedCount} catégorie{flaggedCount > 1 ? 's' : ''} marquée{flaggedCount > 1 ? 's' : ''}</Badge>
                    )}
                </div>
            </Card>

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
                                                <p className="text-[11px] uppercase tracking-wide text-[var(--color-text-muted)] font-medium mb-1.5">
                                                    Gravité <span className="text-red-500">*</span>
                                                </p>
                                                <SeverityPicker value={severity} options={options.severity} onChange={(v) => setSeverity(cat.key, v)} />
                                                {severityErrors[cat.key] && (
                                                    <p className="text-[11px] text-red-500 mt-1">Veuillez sélectionner une gravité.</p>
                                                )}
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

                                            <div className="rounded-lg border border-[var(--color-border)]/70 bg-[var(--color-surface)] p-3 space-y-3">
                                                <div className="flex items-center gap-2">
                                                    <Receipt size={14} className="text-[var(--color-primary)]" />
                                                    <p className="text-[11px] uppercase tracking-wide text-[var(--color-text-muted)] font-medium">
                                                        Coût estimé <span className="text-red-500">*</span>
                                                    </p>
                                                </div>
                                                <div className="grid grid-cols-2 gap-2">
                                                    <div>
                                                        <label className="block text-[11px] text-[var(--color-text-muted)] mb-1">Pièces (FCFA)</label>
                                                        <input
                                                            type="number"
                                                            min="0"
                                                            step="any"
                                                            inputMode="decimal"
                                                            value={form.data.parts_cost[cat.key] ?? ''}
                                                            onChange={(e) => setCost(cat.key, 'parts_cost', e.target.value)}
                                                            placeholder="0"
                                                            className="w-full px-3 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm"
                                                        />
                                                    </div>
                                                    <div>
                                                        <label className="block text-[11px] text-[var(--color-text-muted)] mb-1">Main d'œuvre (FCFA)</label>
                                                        <input
                                                            type="number"
                                                            min="0"
                                                            step="any"
                                                            inputMode="decimal"
                                                            value={form.data.labor_cost[cat.key] ?? ''}
                                                            onChange={(e) => setCost(cat.key, 'labor_cost', e.target.value)}
                                                            placeholder="0"
                                                            className="w-full px-3 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm"
                                                        />
                                                    </div>
                                                </div>
                                                {catTotal(cat.key) > 0 && (
                                                    <div className="flex items-center justify-between text-sm">
                                                        <span className="text-[var(--color-text-muted)]">Total</span>
                                                        <span className="font-bold text-[var(--color-text)]">{catTotal(cat.key).toLocaleString('fr-FR')} FCFA</span>
                                                    </div>
                                                )}

                                                <div>
                                                    <p className="text-[11px] uppercase tracking-wide text-[var(--color-text-muted)] font-medium mb-1.5 flex items-center gap-1.5">
                                                        <Camera size={12} /> Photo (optionnel)
                                                    </p>
                                                    {form.data.attachments[cat.key] ? (
                                                        <div className="flex items-center justify-between gap-2 px-3 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] text-sm">
                                                            <span className="flex items-center gap-1.5 min-w-0">
                                                                <FileText size={14} className="text-[var(--color-primary)] shrink-0" />
                                                                <span className="truncate">{form.data.attachments[cat.key]!.name}</span>
                                                            </span>
                                                            <button
                                                                type="button"
                                                                onClick={() => setAttachment(cat.key, null)}
                                                                className="text-red-500 text-xs font-medium shrink-0"
                                                            >
                                                                Retirer
                                                            </button>
                                                        </div>
                                                    ) : (
                                                        <CameraCapture onCapture={(file) => setAttachment(cat.key, file)} />
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>

                    <Button type="submit" loading={form.processing} className="w-full sm:w-auto mt-5" disabled={!formValid}>
                        <Send size={16} className="mr-2" />
                        {flaggedCount === 0
                            ? 'Sélectionnez au moins une catégorie'
                            : !formValid
                                ? 'Complétez la gravité et les éléments'
                                : `Signaler ${flaggedCount} problème${flaggedCount > 1 ? 's' : ''}`}
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

                                <div className="mt-2 pt-2 border-t border-[var(--color-border)]/50 flex flex-wrap items-center gap-x-3 gap-y-1.5">
                                    {fcfa(issue.total_cost) ? (
                                        <span className="inline-flex items-center gap-1.5 text-sm font-semibold text-[var(--color-text)]">
                                            <Wallet size={14} className="text-[var(--color-primary)]" />
                                            {fcfa(issue.total_cost)}
                                        </span>
                                    ) : (
                                        <span className="text-xs text-[var(--color-text-muted)]">Aucun coût enregistré</span>
                                    )}
                                    {issue.devis_url && (
                                        <a href={issue.devis_url} target="_blank" rel="noopener noreferrer"
                                            className="inline-flex items-center gap-1 text-xs text-[var(--color-primary)] hover:underline">
                                            <FileText size={12} /> Devis
                                        </a>
                                    )}
                                    <Button size="sm" variant="secondary" type="button" className="ml-auto" onClick={() => openCost(issue)}>
                                        <Receipt size={14} className="mr-1" />
                                        {fcfa(issue.total_cost) ? 'Modifier coût' : 'Ajouter coût'}
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </Card>

            <Modal open={!!costIssue} onClose={() => setCostIssue(null)} title="Coût de la réparation">
                <form onSubmit={submitCost} className="space-y-4">
                    {costIssue && (
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="danger">{CATEGORY_LABEL[costIssue.category] ?? costIssue.category}</Badge>
                            {costIssue.severity && (
                                <Badge variant={severityBadgeVariant(costIssue.severity)}>{options.severity[costIssue.severity] ?? costIssue.severity}</Badge>
                            )}
                        </div>
                    )}

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="block text-[11px] uppercase tracking-wide text-[var(--color-text-muted)] font-medium mb-1.5">Pièces (FCFA)</label>
                            <input
                                type="number" min="0" step="0.01" inputMode="decimal"
                                value={costForm.data.parts_cost}
                                onChange={(e) => costForm.setData('parts_cost', e.target.value)}
                                placeholder="0"
                                className="w-full px-3 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm"
                            />
                            {costForm.errors.parts_cost && <p className="mt-1 text-xs text-red-600">{costForm.errors.parts_cost}</p>}
                        </div>
                        <div>
                            <label className="block text-[11px] uppercase tracking-wide text-[var(--color-text-muted)] font-medium mb-1.5">Main d'œuvre (FCFA)</label>
                            <input
                                type="number" min="0" step="0.01" inputMode="decimal"
                                value={costForm.data.labor_cost}
                                onChange={(e) => costForm.setData('labor_cost', e.target.value)}
                                placeholder="0"
                                className="w-full px-3 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm"
                            />
                            {costForm.errors.labor_cost && <p className="mt-1 text-xs text-red-600">{costForm.errors.labor_cost}</p>}
                        </div>
                    </div>

                    <div className="flex items-center justify-between rounded-lg bg-[var(--color-surface-hover)] px-3 py-2">
                        <span className="text-sm text-[var(--color-text-secondary)]">Total</span>
                        <span className="text-base font-bold text-[var(--color-text)]">{costTotal().toLocaleString('fr-FR')} FCFA</span>
                    </div>

                    <div>
                        <label className="block text-[11px] uppercase tracking-wide text-[var(--color-text-muted)] font-medium mb-1.5">Devis (PDF ou photo, optionnel)</label>
                        <input
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png,.webp"
                            onChange={(e) => costForm.setData('devis', e.target.files?.[0] ?? null)}
                            className="block w-full text-sm text-[var(--color-text)] file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-[var(--color-surface-hover)] file:text-[var(--color-text-secondary)]"
                        />
                        {costForm.errors.devis && <p className="mt-1 text-xs text-red-600">{costForm.errors.devis}</p>}
                        {costIssue?.devis_url && !costForm.data.devis && (
                            <a href={costIssue.devis_url} target="_blank" rel="noopener noreferrer"
                                className="mt-1.5 inline-flex items-center gap-1 text-xs text-[var(--color-primary)] hover:underline">
                                <FileText size={12} /> Devis actuel{costIssue.devis_name ? ` — ${costIssue.devis_name}` : ''}
                            </a>
                        )}
                    </div>

                    <div className="flex justify-end gap-2 pt-1">
                        <Button variant="secondary" type="button" onClick={() => setCostIssue(null)}>Annuler</Button>
                        <Button type="submit" loading={costForm.processing}>Enregistrer</Button>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
