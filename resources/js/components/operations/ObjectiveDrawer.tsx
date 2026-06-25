import { router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import Button from '@/components/ui/Button';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import FormInput from '@/components/ui/FormInput';
import FormTextarea from '@/components/ui/FormTextarea';
import type { PlanningMode } from '@/types/achievement';
import { X, Target } from 'lucide-react';
import { clsx } from 'clsx';

const TYPE_LABEL: Record<PlanningMode, string> = { WEEK: 'Semaine', MONTH: 'Mois', YEAR: 'Année', CUSTOM: 'Personnalisé' };
const fmt = (n: number) => Math.round(n).toLocaleString('fr-FR');

interface ParentAllocation { parent_label: string; parent_target: number; allocated: number; remaining: number }
interface ObjectiveConflict { existing_tons: number; new_tons: number }
const ymd = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const mondayOf = (base: Date) => { const d = new Date(base); d.setDate(d.getDate() - ((d.getDay() + 6) % 7)); return d; };

function rangeFor(type: PlanningMode, anchorIso: string, endIso: string): [string, string] {
    const a = new Date(anchorIso + 'T00:00:00');
    if (type === 'WEEK') { const mon = mondayOf(a); const sat = new Date(mon); sat.setDate(mon.getDate() + 5); return [ymd(mon), ymd(sat)]; }
    if (type === 'MONTH') return [ymd(new Date(a.getFullYear(), a.getMonth(), 1)), ymd(new Date(a.getFullYear(), a.getMonth() + 1, 0))];
    if (type === 'YEAR') return [ymd(new Date(a.getFullYear(), 0, 1)), ymd(new Date(a.getFullYear(), 11, 31))];
    return [anchorIso, endIso || anchorIso];
}

export interface ObjectiveDrawerInitial {
    type: PlanningMode;
    start: string;
    end: string;
    target: number;
    notes: string;
}

interface Props {
    onClose: () => void;
    periodTypes: PlanningMode[];
    initial: ObjectiveDrawerInitial;
    editing: boolean;
}

/**
 * Side drawer to create / modify an objective without leaving Planning. Posts to
 * the single objective save path (/logistics/objectives → redirects to /planning,
 * which closes the drawer and refreshes). Period is locked when editing (the
 * upsert is keyed on the period).
 */
export default function ObjectiveDrawer({ onClose, periodTypes, initial, editing }: Props) {
    const [type, setType] = useState<PlanningMode>(initial.type);
    const [anchor, setAnchor] = useState(initial.start);
    const [customEnd, setCustomEnd] = useState(initial.end);
    const [target, setTarget] = useState(initial.target ? String(initial.target) : '');
    const [notes, setNotes] = useState(initial.notes ?? '');
    const [saving, setSaving] = useState(false);
    const [conflict, setConflict] = useState<ObjectiveConflict | null>(null);

    const [start, end] = useMemo(() => rangeFor(type, anchor, customEnd), [type, anchor, customEnd]);
    const targetNum = Number(target) || 0;

    // Live parent-allocation context (validation/visibility only). Refetched when the
    // period changes; the over-allocation warning recomputes client-side as the target
    // changes.
    const [parent, setParent] = useState<ParentAllocation | null>(null);
    useEffect(() => {
        let active = true;
        const url = `/logistics/objectives/parent-allocation?period_type=${type}&start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`;
        fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then((r) => (r.ok ? r.json() : null))
            .then((d) => { if (active) setParent(d); })
            .catch(() => { if (active) setParent(null); });
        return () => { active = false; };
    }, [type, start, end]);

    const overBy = parent ? Math.max(0, parent.allocated + targetNum - parent.parent_target) : 0;

    const save = (override = false) => {
        setSaving(true);
        router.post('/logistics/objectives', {
            period_type: type,
            start_date: type === 'CUSTOM' ? start : anchor,
            end_date: type === 'CUSTOM' ? end : null,
            target_tons: targetNum,
            notes,
            override: override || editing, // editing a locked period is an explicit replace
        }, {
            preserveState: true, // keep the drawer mounted if we bounce back to confirm
            preserveScroll: true,
            onSuccess: (page) => {
                const c = (page.props as { flash?: { objectiveConflict?: ObjectiveConflict } }).flash?.objectiveConflict;
                if (c) { setConflict(c); return; } // existing objective — ask before overwriting
                onClose();
            },
            onFinish: () => setSaving(false),
        });
    };

    return (
        <>
            <div className="fixed inset-0 bg-black/40 z-40" onClick={onClose} />
            <aside className="fixed top-0 right-0 h-full w-full max-w-md bg-[var(--color-surface)] z-50 shadow-xl flex flex-col">
                <header className="flex items-center justify-between px-5 h-16 border-b border-[var(--color-border)]">
                    <div className="flex items-center gap-2">
                        <Target size={18} className="text-[var(--color-primary)]" />
                        <h2 className="font-semibold">{editing ? "Modifier l'objectif" : 'Nouvel objectif'}</h2>
                    </div>
                    <button onClick={onClose} aria-label="Fermer" className="p-1.5 rounded-lg hover:bg-[var(--color-surface-hover)]"><X size={18} /></button>
                </header>

                <div className="flex-1 overflow-y-auto p-5 space-y-4">
                    <div>
                        <span className="block text-xs font-medium text-[var(--color-text-secondary)] mb-1.5">Type de période</span>
                        <div className="flex flex-wrap gap-2">
                            {periodTypes.map((m) => (
                                <button
                                    key={m}
                                    type="button"
                                    disabled={editing}
                                    aria-pressed={type === m}
                                    onClick={() => setType(m)}
                                    className={clsx(
                                        'px-3 h-11 text-sm font-medium rounded-lg border transition-colors',
                                        editing && 'opacity-50 cursor-not-allowed',
                                        type === m
                                            ? 'bg-[var(--color-primary)] text-white border-[var(--color-primary)]'
                                            : 'border-[var(--color-border)] text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)] cursor-pointer',
                                    )}
                                >
                                    {TYPE_LABEL[m]}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <FormInput
                            label={type === 'CUSTOM' ? 'Date de début' : 'Date de référence'}
                            type="date" value={anchor} onChange={(e) => setAnchor(e.target.value)}
                            disabled={editing} className="h-11" wrapperClass="mb-0"
                        />
                        {type === 'CUSTOM' ? (
                            <FormInput label="Date de fin" type="date" value={customEnd} onChange={(e) => setCustomEnd(e.target.value)} disabled={editing} className="h-11" wrapperClass="mb-0" />
                        ) : (
                            <div>
                                <span className="block text-xs font-medium text-[var(--color-text-secondary)] mb-1.5">Période</span>
                                <div className="h-11 flex items-center px-3 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-hover)] text-sm">{start} → {end}</div>
                            </div>
                        )}
                    </div>

                    <div>
                        <label htmlFor="drawer_target" className="block text-xs font-medium text-[var(--color-text-secondary)] mb-1.5">Tonnage à transporter</label>
                        <div className="relative">
                            <input
                                id="drawer_target" type="number" step="0.1" min="0" inputMode="decimal" autoFocus
                                value={target} onChange={(e) => setTarget(e.target.value)} placeholder="0"
                                className="w-full h-12 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] pl-4 pr-10 text-2xl font-bold tabular-nums focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/20 focus:border-[var(--color-primary)]"
                            />
                            <span className="absolute right-4 top-1/2 -translate-y-1/2 text-base font-semibold text-[var(--color-text-muted)]">t</span>
                        </div>
                    </div>

                    {/* Parent-allocation context (validation/visibility only — never blocks) */}
                    {parent && (
                        <div className="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-hover)] p-3 text-sm space-y-1">
                            <div className="flex items-center justify-between gap-2">
                                <span className="text-[var(--color-text-secondary)]">{parent.parent_label}</span>
                                <span className="font-mono font-semibold">{fmt(parent.parent_target)} t</span>
                            </div>
                            <div className="flex items-center justify-between gap-2">
                                <span className="text-[var(--color-text-secondary)]">Déjà alloué</span>
                                <span className="font-mono">{fmt(parent.allocated)} t</span>
                            </div>
                            <div className="flex items-center justify-between gap-2">
                                <span className="text-[var(--color-text-secondary)]">Restant</span>
                                <span className="font-mono font-semibold">{fmt(parent.remaining)} t</span>
                            </div>
                            {overBy > 0 && (
                                <p className="text-amber-600 dark:text-amber-400 pt-1">L'allocation dépasse l'objectif parent de {fmt(overBy)} t.</p>
                            )}
                        </div>
                    )}

                    <FormTextarea label="Note (optionnel)" value={notes} onChange={(e) => setNotes(e.target.value)} rows={2} maxLength={500} wrapperClass="mb-0" />
                </div>

                <footer className="flex items-center justify-end gap-2 px-5 h-16 border-t border-[var(--color-border)]">
                    <Button variant="secondary" onClick={onClose}>Annuler</Button>
                    <Button onClick={() => save()} loading={saving} disabled={targetNum <= 0}>
                        {editing ? 'Enregistrer' : "Créer l'objectif"}
                    </Button>
                </footer>
            </aside>

            <ConfirmDialog
                open={!!conflict}
                onClose={() => setConflict(null)}
                title="Objectif déjà défini"
                message={conflict ? `Un objectif de ${fmt(conflict.existing_tons)} t existe déjà pour cette période. Le remplacer par ${fmt(conflict.new_tons)} t ?` : ''}
                confirmLabel="Remplacer"
                onConfirm={() => save(true)}
            />
        </>
    );
}
