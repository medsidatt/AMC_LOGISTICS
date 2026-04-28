import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import { ShieldCheck, FileEdit, Send, CheckCircle2, XCircle, AlertTriangle, Plus } from 'lucide-react';

interface RecentInspection {
    id: number;
    inspection_date: string | null;
    truck: string | null;
    category: string;
    status: string;
    issues_count: number;
}

interface Props {
    kpis: {
        drafts: number;
        submitted: number;
        validated: number;
        rejected: number;
        open_critical_issues: number;
    };
    recentInspections: RecentInspection[];
    trucksNeedingInspection: { id: number; matricule: string }[];
}

const STATUS_VARIANT: Record<string, 'default' | 'success' | 'warning' | 'danger'> = {
    draft: 'default',
    submitted: 'warning',
    validated: 'success',
    rejected: 'danger',
};

function Kpi({ icon, label, value, variant }: { icon: React.ReactNode; label: string; value: number; variant?: string }) {
    return (
        <Card>
            <div className="flex items-center gap-3">
                <div className={`p-2 rounded-lg ${variant === 'danger' ? 'bg-red-100 text-red-600' : variant === 'warning' ? 'bg-amber-100 text-amber-600' : variant === 'success' ? 'bg-emerald-100 text-emerald-600' : 'bg-slate-100 text-slate-600'}`}>
                    {icon}
                </div>
                <div>
                    <div className="text-xs text-[var(--color-text-muted)] uppercase">{label}</div>
                    <div className="text-2xl font-bold">{value}</div>
                </div>
            </div>
        </Card>
    );
}

export default function HseDashboard({ kpis, recentInspections, trucksNeedingInspection }: Props) {
    return (
        <AuthenticatedLayout>
            <Head title="Tableau de bord HSE" />
            <div className="space-y-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <ShieldCheck size={22} className="text-emerald-500" />
                        <h1 className="text-xl font-semibold">Tableau de bord HSE</h1>
                    </div>
                    <Link href={'/hse/inspections/create'}>
                        <Button><Plus size={16} className="mr-1" /> Nouvelle inspection</Button>
                    </Link>
                </div>

                <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
                    <Kpi icon={<FileEdit size={18} />} label="Brouillons" value={kpis.drafts} />
                    <Kpi icon={<Send size={18} />} label="Soumises" value={kpis.submitted} variant="warning" />
                    <Kpi icon={<CheckCircle2 size={18} />} label="Validées" value={kpis.validated} variant="success" />
                    <Kpi icon={<XCircle size={18} />} label="Rejetées" value={kpis.rejected} variant="danger" />
                    <Kpi icon={<AlertTriangle size={18} />} label="Critiques ouvertes" value={kpis.open_critical_issues} variant="danger" />
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <Card>
                        <h2 className="text-lg font-semibold mb-3">Mes inspections récentes</h2>
                        {recentInspections.length === 0 ? (
                            <p className="text-sm text-[var(--color-text-muted)]">Aucune inspection.</p>
                        ) : (
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-left border-b border-[var(--color-border)]">
                                        <th className="py-1">Date</th>
                                        <th className="py-1">Camion</th>
                                        <th className="py-1">Statut</th>
                                        <th className="py-1">Issues</th>
                                        <th className="py-1"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {recentInspections.map((i) => (
                                        <tr key={i.id} className="border-b border-[var(--color-border)]">
                                            <td className="py-1">{i.inspection_date}</td>
                                            <td className="py-1">{i.truck ?? '—'}</td>
                                            <td className="py-1"><Badge variant={STATUS_VARIANT[i.status] ?? 'default'}>{i.status}</Badge></td>
                                            <td className="py-1">{i.issues_count}</td>
                                            <td className="py-1"><Link href={`/hse/inspections/${i.id}`} className="text-[var(--color-primary)] hover:underline text-xs">Voir</Link></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                        <div className="mt-3">
                            <Link href="/hse/inspections" className="text-sm text-[var(--color-primary)] hover:underline">Voir toutes →</Link>
                        </div>
                    </Card>

                    <Card>
                        <h2 className="text-lg font-semibold mb-3">Camions à inspecter (30+ jours)</h2>
                        {trucksNeedingInspection.length === 0 ? (
                            <p className="text-sm text-[var(--color-text-muted)]">Tous les camions ont été inspectés récemment.</p>
                        ) : (
                            <ul className="space-y-1">
                                {trucksNeedingInspection.map((t) => (
                                    <li key={t.id} className="flex items-center justify-between border-b border-[var(--color-border)] py-1">
                                        <span className="text-sm font-medium">{t.matricule}</span>
                                        <Link href={`${'/hse/inspections/create'}?truck=${t.id}`} className="text-xs text-[var(--color-primary)] hover:underline">Inspecter</Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
