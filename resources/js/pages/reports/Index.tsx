import { Head } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import FormInput from '@/components/ui/FormInput';
import { FileSpreadsheet, Truck, Wrench, Route, AlertTriangle, Calendar, Download, Clock } from 'lucide-react';
import { clsx } from 'clsx';

function ReportSection({ title, description, icon, color, children }: {
    title: string; description: string; icon: React.ReactNode; color: string; children: React.ReactNode;
}) {
    return (
        <div className="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] overflow-hidden">
            <div className={clsx('px-5 py-4 flex items-center gap-3', color)}>
                <div className="p-2 rounded-lg bg-white/20">{icon}</div>
                <div>
                    <h3 className="text-base font-semibold text-white">{title}</h3>
                    <p className="text-xs text-white/70">{description}</p>
                </div>
            </div>
            <div className="p-5">{children}</div>
        </div>
    );
}

function ExcelBtn({ href, label }: { href: string; label: string }) {
    return (
        <a href={href} className="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium transition shadow-sm">
            <Download size={16} /> {label}
        </a>
    );
}

export default function ReportsIndex() {
    const [tFrom, setTFrom] = useState('');
    const [tTo, setTTo] = useState('');
    const [mFrom, setMFrom] = useState('');
    const [mTo, setMTo] = useState('');

    const tq = [tFrom && `from=${tFrom}`, tTo && `to=${tTo}`].filter(Boolean).join('&');
    const mq = [mFrom && `from=${mFrom}`, mTo && `to=${mTo}`].filter(Boolean).join('&');

    return (
        <AuthenticatedLayout title="Rapports">
            <Head title="Rapports" />

            <div className="mb-6">
                <h1 className="text-2xl font-bold text-[var(--color-text)]">Centre de rapports</h1>
                <p className="text-sm text-[var(--color-text-muted)] mt-1">Exportez vos données en fichiers Excel pour analyse externe</p>
            </div>

            <div className="grid lg:grid-cols-2 gap-6">
                {/* Transport */}
                <ReportSection
                    title="Suivi Transport"
                    description="Rotations, poids, écarts — période 22→21"
                    icon={<Route size={20} className="text-white" />}
                    color="bg-[var(--color-primary)]"
                >
                    <div className="grid grid-cols-2 gap-3 mb-4">
                        <FormInput label="Du" type="date" name="tf" value={tFrom} onChange={(e) => setTFrom(e.target.value)} />
                        <FormInput label="Au" type="date" name="tt" value={tTo} onChange={(e) => setTTo(e.target.value)} />
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <ExcelBtn href={`/reports/transport/excel${tq ? '?' + tq : ''}`} label="Exporter les rotations" />
                    </div>
                    <p className="text-xs text-[var(--color-text-muted)] mt-3">Inclut : référence, dates, camion, conducteur, fournisseur, poids brut/tare/net, écart</p>
                </ReportSection>

                {/* Fleet */}
                <ReportSection
                    title="Flotte de camions"
                    description="État complet de la flotte avec maintenance et GPS"
                    icon={<Truck size={20} className="text-white" />}
                    color="bg-cyan-600"
                >
                    <div className="flex flex-wrap gap-2">
                        <ExcelBtn href="/reports/fleet/excel?active_only=true" label="Camions actifs" />
                        <ExcelBtn href="/reports/fleet/excel?active_only=false" label="Tous les camions" />
                    </div>
                    <p className="text-xs text-[var(--color-text-muted)] mt-3">Inclut : matricule, transporteur, km, maintenance, GPS Fleeti, dernière sync</p>
                </ReportSection>

                {/* Maintenance History */}
                <ReportSection
                    title="Historique maintenance"
                    description="Toutes les maintenances enregistrées"
                    icon={<Wrench size={20} className="text-white" />}
                    color="bg-emerald-600"
                >
                    <div className="grid grid-cols-2 gap-3 mb-4">
                        <FormInput label="Du" type="date" name="mf" value={mFrom} onChange={(e) => setMFrom(e.target.value)} />
                        <FormInput label="Au" type="date" name="mt" value={mTo} onChange={(e) => setMTo(e.target.value)} />
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <ExcelBtn href={`/reports/maintenance/excel${mq ? '?' + mq : ''}`} label="Exporter l'historique" />
                    </div>
                    <p className="text-xs text-[var(--color-text-muted)] mt-3">Inclut : date, camion, type, km, seuil prévu, intervalle règle, notes</p>
                </ReportSection>

                {/* Idle Hourly */}
                <ReportSection
                    title="Ralenti horaire"
                    description="Heures moteur tournant à l'arrêt — carrière vs route"
                    icon={<Clock size={20} className="text-white" />}
                    color="bg-violet-600"
                >
                    <div className="flex flex-wrap gap-2">
                        <a
                            href="/reports/idle-hourly"
                            className="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium transition shadow-sm"
                        >
                            <Clock size={16} /> Ouvrir le rapport
                        </a>
                    </div>
                    <p className="text-xs text-[var(--color-text-muted)] mt-3">Inclut : camion, date, heure, minutes ralenti, lieu (carrière/site/route), coordonnées</p>
                </ReportSection>

                {/* Maintenance Due */}
                <ReportSection
                    title="Maintenance requise"
                    description="Camions nécessitant une intervention"
                    icon={<AlertTriangle size={20} className="text-white" />}
                    color="bg-amber-600"
                >
                    <div className="flex flex-wrap gap-2">
                        <ExcelBtn href="/reports/maintenance-due/excel?only_due=true" label="Urgents seulement" />
                        <ExcelBtn href="/reports/maintenance-due/excel?only_due=false" label="Tous avec état" />
                    </div>
                    <p className="text-xs text-[var(--color-text-muted)] mt-3">Inclut : matricule, type maintenance, intervalle, compteur, restant, dernière date</p>
                </ReportSection>
            </div>
        </AuthenticatedLayout>
    );
}
