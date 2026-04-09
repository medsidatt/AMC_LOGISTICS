import { Head } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import FormInput from '@/components/ui/FormInput';
import { FileSpreadsheet, FileText, Truck, Wrench, Route, Download, AlertTriangle } from 'lucide-react';

function ReportCard({ title, description, icon, children }: { title: string; description: string; icon: React.ReactNode; children: React.ReactNode }) {
    return (
        <Card>
            <div className="flex items-start gap-3 mb-4">
                <div className="p-2.5 rounded-xl bg-[var(--color-primary)]/10 shrink-0">{icon}</div>
                <div>
                    <h3 className="text-lg font-semibold text-[var(--color-text)]">{title}</h3>
                    <p className="text-xs text-[var(--color-text-muted)] mt-0.5">{description}</p>
                </div>
            </div>
            {children}
        </Card>
    );
}

function DownloadButton({ href, label, variant, icon }: { href: string; label: string; variant: 'excel' | 'pdf'; icon?: React.ReactNode }) {
    const color = variant === 'excel' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-red-600 hover:bg-red-700';
    return (
        <a href={href} className={`inline-flex items-center gap-2 px-4 py-2 rounded-lg text-white text-sm font-medium transition ${color}`}>
            {icon ?? (variant === 'excel' ? <FileSpreadsheet size={16} /> : <FileText size={16} />)}
            {label}
        </a>
    );
}

export default function ReportsIndex() {
    const [transportFrom, setTransportFrom] = useState('');
    const [transportTo, setTransportTo] = useState('');
    const [maintenanceFrom, setMaintenanceFrom] = useState('');
    const [maintenanceTo, setMaintenanceTo] = useState('');

    const transportParams = new URLSearchParams();
    if (transportFrom) transportParams.set('from', transportFrom);
    if (transportTo) transportParams.set('to', transportTo);
    const tq = transportParams.toString() ? '?' + transportParams.toString() : '';

    const maintenanceParams = new URLSearchParams();
    if (maintenanceFrom) maintenanceParams.set('from', maintenanceFrom);
    if (maintenanceTo) maintenanceParams.set('to', maintenanceTo);
    const mq = maintenanceParams.toString() ? '?' + maintenanceParams.toString() : '';

    return (
        <AuthenticatedLayout title="Rapports">
            <Head title="Rapports" />

            <div className="grid lg:grid-cols-2 gap-6">
                {/* Transport Tracking */}
                <ReportCard
                    title="Suivi Transport"
                    description="Rotations, poids fournisseur/client, écarts par période (22→21)"
                    icon={<Route size={20} className="text-[var(--color-primary)]" />}
                >
                    <div className="grid grid-cols-2 gap-3 mb-4">
                        <FormInput label="Du" type="date" name="from" value={transportFrom} onChange={(e) => setTransportFrom(e.target.value)} />
                        <FormInput label="Au" type="date" name="to" value={transportTo} onChange={(e) => setTransportTo(e.target.value)} />
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <DownloadButton href={`/reports/transport/excel${tq}`} label="Excel" variant="excel" />
                        <DownloadButton href={`/reports/transport/pdf${tq}`} label="PDF" variant="pdf" />
                    </div>
                </ReportCard>

                {/* Fleet */}
                <ReportCard
                    title="Flotte de camions"
                    description="Tous les camions avec compteur km, état maintenance, GPS Fleeti"
                    icon={<Truck size={20} className="text-[var(--color-info)]" />}
                >
                    <div className="flex flex-wrap gap-2">
                        <DownloadButton href="/reports/fleet/excel?active_only=true" label="Camions actifs (Excel)" variant="excel" />
                        <DownloadButton href="/reports/fleet/excel?active_only=false" label="Tous les camions (Excel)" variant="excel" />
                    </div>
                </ReportCard>

                {/* Maintenance History */}
                <ReportCard
                    title="Historique maintenance"
                    description="Toutes les maintenances effectuées avec km, règle, notes"
                    icon={<Wrench size={20} className="text-emerald-500" />}
                >
                    <div className="grid grid-cols-2 gap-3 mb-4">
                        <FormInput label="Du" type="date" name="mfrom" value={maintenanceFrom} onChange={(e) => setMaintenanceFrom(e.target.value)} />
                        <FormInput label="Au" type="date" name="mto" value={maintenanceTo} onChange={(e) => setMaintenanceTo(e.target.value)} />
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <DownloadButton href={`/reports/maintenance/excel${mq}`} label="Historique (Excel)" variant="excel" />
                    </div>
                </ReportCard>

                {/* Maintenance Due */}
                <ReportCard
                    title="Maintenance requise"
                    description="Camions nécessitant une maintenance urgente ou à prévoir"
                    icon={<AlertTriangle size={20} className="text-amber-500" />}
                >
                    <div className="flex flex-wrap gap-2">
                        <DownloadButton href="/reports/maintenance-due/excel?only_due=true" label="Urgents seulement (Excel)" variant="excel" />
                        <DownloadButton href="/reports/maintenance-due/excel?only_due=false" label="Tous les camions (Excel)" variant="excel" />
                    </div>
                </ReportCard>
            </div>
        </AuthenticatedLayout>
    );
}
