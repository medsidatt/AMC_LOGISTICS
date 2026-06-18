import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import AchievementSummary from '@/components/logistics/AchievementSummary';
import type { Achievement } from '@/types/achievement';
import { ArrowLeft, ChevronLeft, ChevronRight, Trophy, AlertTriangle } from 'lucide-react';

interface Props {
    period: { start: string; end: string };
    achievement: Achievement;
}

const fmt = (n: number) => Math.round(n).toLocaleString('fr-FR');

function shiftWeek(iso: string, weeks: number): string {
    const d = new Date(iso + 'T00:00:00');
    d.setDate(d.getDate() + weeks * 7);
    return d.toISOString().slice(0, 10);
}

export default function PlanningWeekly({ period, achievement }: Props) {
    const goto = (start: string) => router.get('/logistics/planning/weekly', { start }, { preserveState: false });

    return (
        <AuthenticatedLayout>
            <Head title="Tableau hebdomadaire" />
            <div className="space-y-5">
                <div className="flex items-start justify-between gap-3 flex-wrap">
                    <div>
                        <div className="flex items-center gap-2">
                            <Trophy size={22} className="text-[var(--color-primary)]" />
                            <h1 className="text-xl font-semibold">Tableau hebdomadaire</h1>
                        </div>
                        <p className="text-sm text-[var(--color-text-muted)] mt-1">Rotations planifiées vs réalisées — semaine du {period.start} au {period.end}.</p>
                    </div>
                    <Button variant="secondary" onClick={() => router.visit('/logistics/planning')}>
                        <ArrowLeft size={14} className="mr-1" /> Programmation
                    </Button>
                </div>

                <Card>
                    <div className="flex items-center gap-2 mb-4">
                        <button type="button" onClick={() => goto(shiftWeek(period.start, -1))} className="p-2 rounded-lg hover:bg-[var(--color-surface-hover)]" title="Semaine précédente"><ChevronLeft size={18} /></button>
                        <span className="text-sm font-medium">{period.start} → {period.end}</span>
                        <button type="button" onClick={() => goto(shiftWeek(period.start, 1))} className="p-2 rounded-lg hover:bg-[var(--color-surface-hover)]" title="Semaine suivante"><ChevronRight size={18} /></button>
                    </div>
                    <AchievementSummary fleet={achievement.fleet} projection={achievement.projection} gpsAvailable={achievement.gps_available} />
                </Card>

                <Card padding={false}>
                    <div className="px-4 pt-4 pb-2 font-semibold">Réalisation par camion</div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-[var(--color-surface-hover)] text-[11px] uppercase tracking-wide text-[var(--color-text-secondary)]">
                                    <th className="px-4 py-3 text-left font-semibold">Camion</th>
                                    <th className="px-4 py-3 text-right font-semibold">Planifié</th>
                                    <th className="px-4 py-3 text-right font-semibold">Réalisé</th>
                                    <th className="px-4 py-3 text-right font-semibold">Restant</th>
                                    <th className="px-4 py-3 text-left font-semibold w-32">Avancement</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--color-border)]">
                                {achievement.per_truck.length === 0 ? (
                                    <tr><td colSpan={5} className="px-4 py-10 text-center text-[var(--color-text-muted)]">Aucun objectif pour cette semaine.</td></tr>
                                ) : achievement.per_truck.map((t) => (
                                    <tr key={t.truck_id} className="hover:bg-[var(--color-surface-hover)]/40">
                                        <td className="px-4 py-3 font-medium">{t.matricule}</td>
                                        <td className="px-4 py-3 text-right font-mono">{fmt(t.target_rotations)} rot</td>
                                        <td className="px-4 py-3 text-right font-mono">
                                            {fmt(t.done_rotations)} rot
                                            {t.missing_tickets > 0 && <Badge variant="warning" className="ml-1"><AlertTriangle size={10} className="mr-0.5" />{t.missing_tickets}</Badge>}
                                        </td>
                                        <td className="px-4 py-3 text-right font-mono text-amber-600 dark:text-amber-400">{fmt(t.remaining_rotations)} rot</td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-2">
                                                <div className="flex-1 h-2 rounded-full bg-[var(--color-surface-hover)] overflow-hidden">
                                                    <div className="h-full rounded-full bg-[var(--color-primary)]" style={{ width: `${Math.min(100, t.pct ?? 0)}%` }} />
                                                </div>
                                                <span className="text-xs font-semibold w-8 text-right">{t.pct ?? '—'}</span>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>

                {achievement.missing_ticket_list.length > 0 && (
                    <Card>
                        <h2 className="font-semibold flex items-center gap-2 mb-3 text-amber-600 dark:text-amber-400">
                            <AlertTriangle size={18} /> Tickets manquants ({achievement.fleet.missing_tickets})
                        </h2>
                        <p className="text-xs text-[var(--color-text-muted)] mb-3">Rotations détectées par GPS (carrière → retour) sans bon de transport saisi.</p>
                        <div className="space-y-1.5">
                            {achievement.missing_ticket_list.map((m, i) => (
                                <div key={i} className="flex items-center justify-between text-sm rounded-lg border border-[var(--color-border)] px-3 py-2">
                                    <span className="font-medium">{m.matricule}</span>
                                    <span className="text-[var(--color-text-muted)]">{m.date} · {fmt(m.distance_km)} km</span>
                                </div>
                            ))}
                        </div>
                    </Card>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
