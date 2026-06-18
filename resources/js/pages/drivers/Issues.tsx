import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import CameraCapture from '@/components/inspection/CameraCapture';
import BigChoice, { BigChoiceOption } from '@/components/drivers/BigChoice';
import HelpHint from '@/components/drivers/HelpHint';
import { AlertTriangle, Send, Truck as TruckIcon, CheckCircle2, Check, FileText, Wallet, Camera } from 'lucide-react';
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

const SEVERITY_VARIANT: Record<string, 'primary' | 'warning' | 'danger'> = {
    minor: 'primary',
    major: 'warning',
    critical: 'danger',
};

/* ── Tire diagram (no technical axle codes) ── */
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
    const tireBtn = (n: number) => (
        <button
            key={n}
            type="button"
            onClick={() => onToggle(`tire_${n}`)}
            className={clsx(
                'w-11 h-14 rounded-lg text-sm font-bold border-2 transition shrink-0',
                isSelected(n)
                    ? 'border-red-500 bg-red-500 text-white'
                    : 'border-[var(--color-border)] bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)]',
            )}
        >{n}</button>
    );
    return (
        <div className="rounded-lg border border-dashed border-[var(--color-border)] p-3 bg-[var(--color-surface)]">
            <div className="text-center text-[11px] uppercase tracking-wide text-[var(--color-text-muted)] mb-2">Avant ↑</div>
            <div className="flex flex-col items-center gap-2">
                {axles.map((axle, idx) => {
                    const left = axle.type === 'steering' ? [axle.tires[0]] : axle.tires.slice(0, axle.tires.length / 2);
                    const right = axle.type === 'steering' ? [axle.tires[1]] : axle.tires.slice(axle.tires.length / 2);
                    return (
                        <div key={idx} className="flex items-center w-full justify-center gap-1">
                            <div className="flex items-center gap-1">{left.map(tireBtn)}</div>
                            <div className="flex-1 max-w-[120px] mx-2 h-1.5 bg-[var(--color-border)] rounded-full" />
                            <div className="flex items-center gap-1">{right.map(tireBtn)}</div>
                        </div>
                    );
                })}
            </div>
            <div className="text-center text-[11px] uppercase tracking-wide text-[var(--color-text-muted)] mt-2">Arrière ↓</div>
        </div>
    );
}

function ChipsMultiSelect({ options, selected, onToggle }: {
    options: Record<string, string>;
    selected: string[];
    onToggle: (key: string) => void;
}) {
    return (
        <div className="flex flex-wrap gap-2">
            {Object.entries(options).map(([key, lbl]) => {
                const active = selected.includes(key);
                return (
                    <button
                        key={key}
                        type="button"
                        onClick={() => onToggle(key)}
                        className={clsx(
                            'px-4 py-2.5 rounded-lg text-sm font-medium border-2 transition min-h-[44px]',
                            active
                                ? 'border-red-500 bg-red-500/15 text-red-700 dark:text-red-300'
                                : 'border-[var(--color-border)] bg-[var(--color-surface)] text-[var(--color-text-secondary)]',
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
        attachments: {} as Record<string, File | null>,
    });

    const fcfa = (v: string | null) =>
        v == null || v === '' ? null : `${Number(v).toLocaleString('fr-FR')} FCFA`;

    const severityOptions: BigChoiceOption[] = Object.entries(options.severity).map(([value, label]) => ({
        value,
        label,
        variant: SEVERITY_VARIANT[value] ?? 'primary',
    }));

    const isFlagged = (cat: string) => form.data.flagged.includes(cat);

    const toggleCategory = (cat: string) => {
        const turningOn = !isFlagged(cat);
        const next = turningOn
            ? [...form.data.flagged, cat]
            : form.data.flagged.filter((c) => c !== cat);
        form.setData('flagged', next);

        // Pre-select the lowest severity so a status is always set.
        if (turningOn && !form.data.severity[cat]) {
            form.setData('severity', { ...form.data.severity, [cat]: DEFAULT_SEVERITY });
        }
    };

    const setSeverity = (cat: string, value: string) => {
        form.setData('severity', { ...form.data.severity, [cat]: value });
    };

    const setNotes = (cat: string, value: string) => {
        form.setData('notes', { ...form.data.notes, [cat]: value });
    };

    const setAttachment = (cat: string, file: File | null) => {
        form.setData('attachments', { ...form.data.attachments, [cat]: file });
    };

    const togglePosition = (cat: string, pos: string) => {
        const current = form.data.positions[cat] ?? [];
        const next = current.includes(pos) ? current.filter((p) => p !== pos) : [...current, pos];
        form.setData('positions', { ...form.data.positions, [cat]: next });
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/drivers/issues', {
            forceFormData: true,
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

    // Submittable when ≥1 category is checked and each checked category has a
    // severity and (for tires/lights) at least one position. No cost required.
    const requiresPositions = (cat: string) => cat === 'tires' || cat === 'lights';
    const formValid =
        flaggedCount > 0 &&
        form.data.flagged.every((cat) => {
            if (!form.data.severity[cat]) return false;
            if (requiresPositions(cat) && (form.data.positions[cat]?.length ?? 0) === 0) return false;
            return true;
        });

    return (
        <AuthenticatedLayout title="Signaler un problème">
            <Head title="Signaler un problème" />

            <HelpHint id="driver-issues">
                Cochez ce qui ne va pas, choisissez la gravité, et ajoutez une photo si possible. Pas besoin d'indiquer un coût.
            </HelpHint>

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
                        <Badge variant="warning">{flaggedCount} problème{flaggedCount > 1 ? 's' : ''}</Badge>
                    )}
                </div>
            </Card>

            <Card className="mb-6">
                <form onSubmit={submit}>
                    <div className="flex items-center gap-2 mb-4">
                        <AlertTriangle size={18} className="text-amber-500" />
                        <h2 className="font-semibold text-[var(--color-text)]">Cochez ce qui ne va pas</h2>
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
                                        flagged ? 'border-red-400 bg-red-500/5' : 'border-[var(--color-border)]',
                                    )}
                                >
                                    <button
                                        type="button"
                                        onClick={() => toggleCategory(cat.key)}
                                        className="flex items-center gap-3 w-full p-4 text-left"
                                    >
                                        <div className={clsx(
                                            'w-6 h-6 rounded border-2 flex items-center justify-center transition shrink-0',
                                            flagged ? 'border-red-500 bg-red-500 text-white' : 'border-[var(--color-border)]',
                                        )}>
                                            {flagged && <Check size={16} strokeWidth={3} />}
                                        </div>
                                        <span className="font-medium text-[var(--color-text)]">{cat.label}</span>
                                    </button>

                                    {flagged && (
                                        <div className="px-4 pb-4 space-y-4 border-t border-[var(--color-border)]/50 pt-4">
                                            <div>
                                                <p className="text-xs uppercase tracking-wide text-[var(--color-text-muted)] font-medium mb-2">Gravité</p>
                                                <BigChoice value={severity} options={severityOptions} onChange={(v) => setSeverity(cat.key, v)} />
                                            </div>
                                            {cat.key === 'tires' && (
                                                <div>
                                                    <p className="text-xs uppercase tracking-wide text-[var(--color-text-muted)] font-medium mb-2">Touchez le(s) pneu(s) concerné(s)</p>
                                                    <TireDiagram tireCount={truck.tire_count || 26} selected={positions} onToggle={(pos) => togglePosition(cat.key, pos)} />
                                                </div>
                                            )}
                                            {cat.key === 'lights' && (
                                                <div>
                                                    <p className="text-xs uppercase tracking-wide text-[var(--color-text-muted)] font-medium mb-2">Feu(x) concerné(s)</p>
                                                    <ChipsMultiSelect options={options.light_positions} selected={positions} onToggle={(pos) => togglePosition(cat.key, pos)} />
                                                </div>
                                            )}
                                            <input
                                                type="text"
                                                value={notes}
                                                onChange={(e) => setNotes(cat.key, e.target.value)}
                                                placeholder={`Détails (${cat.label.toLowerCase()})...`}
                                                className="w-full px-3 py-3 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm"
                                                maxLength={500}
                                            />

                                            <div>
                                                <p className="text-xs uppercase tracking-wide text-[var(--color-text-muted)] font-medium mb-2 flex items-center gap-1.5">
                                                    <Camera size={13} /> Photo (optionnel)
                                                </p>
                                                {form.data.attachments[cat.key] ? (
                                                    <div className="flex items-center justify-between gap-2 px-3 py-2.5 rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] text-sm">
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
                                    )}
                                </div>
                            );
                        })}
                    </div>

                    <Button type="submit" loading={form.processing} className="w-full mt-5 min-h-[52px]" disabled={!formValid}>
                        <Send size={16} className="mr-2" />
                        {flaggedCount === 0
                            ? 'Sélectionnez au moins un problème'
                            : !formValid
                                ? 'Choisissez la gravité et les éléments'
                                : `Envoyer ${flaggedCount} problème${flaggedCount > 1 ? 's' : ''}`}
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
                                        : 'border-[var(--color-border)]',
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

                                {(fcfa(issue.total_cost) || issue.devis_url) && (
                                    <div className="mt-2 pt-2 border-t border-[var(--color-border)]/50 flex flex-wrap items-center gap-x-3 gap-y-1.5">
                                        {fcfa(issue.total_cost) && (
                                            <span className="inline-flex items-center gap-1.5 text-sm font-semibold text-[var(--color-text)]">
                                                <Wallet size={14} className="text-[var(--color-primary)]" />
                                                {fcfa(issue.total_cost)}
                                            </span>
                                        )}
                                        {issue.devis_url && (
                                            <a href={issue.devis_url} target="_blank" rel="noopener noreferrer"
                                                className="inline-flex items-center gap-1 text-xs text-[var(--color-primary)] hover:underline">
                                                <FileText size={12} /> Devis
                                            </a>
                                        )}
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
