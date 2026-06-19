import { clsx } from 'clsx';
import { TrendingUp, AlertTriangle, CheckCircle2, Target } from 'lucide-react';
import type { FleetAchievement, Projection } from '@/types/achievement';

const fmt = (n: number) => Math.round(n).toLocaleString('fr-FR');

/** Reusable fleet objective summary: progress + projection + remaining. */
export default function AchievementSummary({ fleet, projection, gpsAvailable }: {
    fleet: FleetAchievement;
    projection: Projection;
    gpsAvailable?: boolean;
}) {
    const pct = fleet.pct ?? 0;
    const done = pct >= 100;

    return (
        <div className="space-y-4">
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <Stat icon={<Target size={16} />} label="Objectif" value={`${fmt(fleet.target_tons)} t`} sub={`${fmt(fleet.target_rotations)} rotations`} />
                <Stat icon={<CheckCircle2 size={16} className="text-emerald-500" />} label="Réalisé" value={`${fmt(fleet.done_tons)} t`} sub={`${fmt(fleet.done_rotations)} rotations`} />
                <Stat icon={<TrendingUp size={16} />} label="Restant" value={`${fmt(fleet.remaining_tons)} t`} sub={`${fmt(fleet.remaining_rotations)} rotations`} />
                <Stat
                    icon={projection.on_track ? <CheckCircle2 size={16} className="text-emerald-500" /> : <AlertTriangle size={16} className="text-amber-500" />}
                    label="Projection"
                    value={`${fmt(projection.projected_tons)} t`}
                    sub={projection.on_track ? 'En bonne voie' : 'En retard'}
                />
            </div>

            <div>
                <div className="flex items-center justify-between text-sm mb-1">
                    <span className="text-[var(--color-text-secondary)]">Avancement</span>
                    <span className="font-semibold">{fleet.pct ?? 0}%</span>
                </div>
                <div className="h-2.5 rounded-full bg-[var(--color-surface-hover)] overflow-hidden">
                    <div
                        className={clsx('h-full rounded-full', done ? 'bg-emerald-500' : 'bg-[var(--color-primary)]')}
                        style={{ width: `${Math.min(100, pct)}%` }}
                    />
                </div>
                <div className="flex items-center gap-3 mt-2 text-xs text-[var(--color-text-muted)]">
                    <span>Jour {projection.days_elapsed}/{projection.days_total}</span>
                    <span>· Rythme {projection.pace_rotations_per_day}/j</span>
                    {fleet.missing_tickets > 0 && (
                        <span className="inline-flex items-center gap-1 text-amber-600 dark:text-amber-400">
                            <AlertTriangle size={12} /> {fleet.missing_tickets} bon{fleet.missing_tickets > 1 ? 's' : ''} manquant{fleet.missing_tickets > 1 ? 's' : ''}
                        </span>
                    )}
                </div>
            </div>
        </div>
    );
}

function Stat({ icon, label, value, sub }: { icon: React.ReactNode; label: string; value: string; sub?: string }) {
    return (
        <div className="rounded-xl border border-[var(--color-border)] p-3">
            <div className="flex items-center gap-1.5 text-xs text-[var(--color-text-muted)] uppercase tracking-wide">{icon}{label}</div>
            <div className="text-xl font-bold text-[var(--color-text)] mt-1 leading-tight">{value}</div>
            {sub && <div className="text-xs text-[var(--color-text-muted)] mt-0.5">{sub}</div>}
        </div>
    );
}
