import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import DataTable from '@/components/ui/DataTable';
import { ArrowLeft, Pencil, Truck as TruckIcon, Gauge, Fuel, Wifi, WifiOff, Wrench, Calendar } from 'lucide-react';
import { clsx } from 'clsx';

interface Tracking {
    id: number;
    reference: string;
    driver: string | null;
    provider: string | null;
    provider_net_weight: number | null;
    client_net_weight: number | null;
    gap: number | null;
    client_date: string | null;
    provider_date: string | null;
}

interface Maintenance {
    id: number;
    maintenance_date: string;
    maintenance_type: string;
    kilometers_at_maintenance: number | null;
    trigger_km: number | null;
    notes: string | null;
}

interface Props {
    truck: {
        id: number;
        matricule: string;
        transporter: string | null;
        maintenance_type: string;
        is_active: boolean;
        total_kilometers: number;
        km_maintenance_interval: number | null;
        fleeti_asset_id: string | null;
        fleeti_gateway_id: string | null;
        fleeti_last_kilometers: number | null;
        fleeti_last_fuel_level: number | null;
        fleeti_last_synced_at: string | null;
        created_at: string | null;
        updated_at: string | null;
    };
    maintenanceInfo: Record<string, any>;
    recentTrackings: Tracking[];
    maintenances: Maintenance[];
}

const fmt = (v: number | null | undefined) => (v == null ? '-' : v.toLocaleString('fr-FR', { maximumFractionDigits: 2 }));

function InfoItem({ label, value, icon }: { label: string; value: React.ReactNode; icon?: React.ReactNode }) {
    return (
        <div className="p-3 rounded-lg bg-[var(--color-surface-hover)]">
            <div className="flex items-center gap-1.5 text-[var(--color-text-muted)] mb-1">
                {icon}
                <p className="text-xs uppercase">{label}</p>
            </div>
            <p className="text-sm font-medium text-[var(--color-text)]">{value || '-'}</p>
        </div>
    );
}

export default function TrucksShow({ truck, recentTrackings, maintenances }: Props) {
    const fuelLevel = truck.fleeti_last_fuel_level;
    const fuelColor = fuelLevel == null ? 'muted' : fuelLevel < 30 ? 'danger' : fuelLevel < 80 ? 'warning' : 'success';

    return (
        <AuthenticatedLayout title={truck.matricule}>
            <Head title={truck.matricule} />

            <div className="flex items-center justify-between mb-4">
                <Button variant="ghost" icon={<ArrowLeft size={16} />} onClick={() => window.history.back()}>
                    Retour
                </Button>
                <Button variant="secondary" icon={<Pencil size={16} />} onClick={() => window.location.href = `/trucks/${truck.id}/edit-page`}>
                    Modifier
                </Button>
            </div>

            {/* Header Card */}
            <Card className="mb-6">
                <div className="flex flex-col sm:flex-row items-center gap-4">
                    <div className="w-20 h-20 rounded-full bg-[var(--color-primary)]/10 flex items-center justify-center shrink-0">
                        <TruckIcon size={32} className="text-[var(--color-primary)]" />
                    </div>
                    <div className="flex-1 text-center sm:text-left">
                        <h2 className="text-2xl font-bold text-[var(--color-text)]">{truck.matricule}</h2>
                        <p className="text-sm text-[var(--color-text-secondary)] mt-1">{truck.transporter ?? 'Sans transporteur'}</p>
                        <div className="flex flex-wrap items-center justify-center sm:justify-start gap-2 mt-2">
                            <Badge variant={truck.is_active ? 'success' : 'muted'}>{truck.is_active ? 'Actif' : 'Inactif'}</Badge>
                            {truck.fleeti_asset_id
                                ? <Badge variant="info"><Wifi size={12} className="inline mr-1" /> GPS connecté</Badge>
                                : <Badge variant="muted"><WifiOff size={12} className="inline mr-1" /> Sans GPS</Badge>}
                            <Badge variant="primary">{truck.maintenance_type}</Badge>
                        </div>
                    </div>
                    {fuelLevel != null && (
                        <div className="text-center sm:text-right p-4 rounded-xl bg-[var(--color-surface-hover)]">
                            <div className="flex items-center justify-center sm:justify-end gap-1.5 text-[var(--color-text-muted)]">
                                <Fuel size={14} />
                                <span className="text-xs uppercase">Carburant</span>
                            </div>
                            <p className={clsx('text-2xl font-bold mt-1',
                                fuelColor === 'danger' && 'text-red-600',
                                fuelColor === 'warning' && 'text-amber-600',
                                fuelColor === 'success' && 'text-emerald-600',
                            )}>{fuelLevel.toFixed(1)} L</p>
                        </div>
                    )}
                </div>
            </Card>

            {/* Info Grid */}
            <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
                <InfoItem label="Compteur total" icon={<Gauge size={12} />} value={`${fmt(truck.total_kilometers)} km`} />
                <InfoItem label="Intervalle maintenance" icon={<Wrench size={12} />} value={truck.km_maintenance_interval ? `${fmt(truck.km_maintenance_interval)} km` : '-'} />
                <InfoItem label="Fleeti km" icon={<Gauge size={12} />} value={truck.fleeti_last_kilometers ? `${fmt(truck.fleeti_last_kilometers)} km` : '-'} />
                <InfoItem label="Dernière sync Fleeti" icon={<Calendar size={12} />} value={truck.fleeti_last_synced_at ?? '-'} />
                <InfoItem label="Asset ID Fleeti" value={truck.fleeti_asset_id ?? '-'} />
                <InfoItem label="Gateway ID" value={truck.fleeti_gateway_id ?? '-'} />
                <InfoItem label="Créé le" icon={<Calendar size={12} />} value={truck.created_at ?? '-'} />
                <InfoItem label="Modifié le" icon={<Calendar size={12} />} value={truck.updated_at ?? '-'} />
            </div>

            {/* Recent transports */}
            <Card className="mb-6">
                <h3 className="text-lg font-semibold text-[var(--color-text)] mb-4">Derniers transports</h3>
                <DataTable
                    data={recentTrackings}
                    columns={[
                        { key: 'reference', label: 'Référence' },
                        { key: 'client_date', label: 'Date client', render: (r) => r.client_date ?? '-' },
                        { key: 'driver', label: 'Conducteur', hideOnMobile: true },
                        { key: 'provider', label: 'Fournisseur', hideOnMobile: true },
                        { key: 'provider_net_weight', label: 'Poids Fourn.', render: (r) => fmt(r.provider_net_weight) },
                        { key: 'client_net_weight', label: 'Poids Client', render: (r) => fmt(r.client_net_weight) },
                        {
                            key: 'gap', label: 'Perte / Exc.',
                            render: (r) => {
                                const g = r.gap ?? 0;
                                if (g < 0) return <Badge variant="danger">Perte {fmt(Math.abs(g))}</Badge>;
                                if (g > 0) return <Badge variant="info">Exc. +{fmt(g)}</Badge>;
                                return <Badge variant="success">OK</Badge>;
                            },
                        },
                    ]}
                    searchable={false}
                    emptyMessage="Aucun transport récent"
                />
            </Card>

            {/* Maintenance history */}
            <Card>
                <h3 className="text-lg font-semibold text-[var(--color-text)] mb-4">Historique maintenance</h3>
                <DataTable
                    data={maintenances}
                    columns={[
                        { key: 'maintenance_date', label: 'Date', render: (r) => r.maintenance_date ?? '-' },
                        { key: 'maintenance_type', label: 'Type' },
                        { key: 'kilometers_at_maintenance', label: 'Km effectués', render: (r) => fmt(r.kilometers_at_maintenance) },
                        { key: 'trigger_km', label: 'Seuil prévu', render: (r) => fmt(r.trigger_km) },
                        { key: 'notes', label: 'Notes', hideOnMobile: true, render: (r) => r.notes ?? '-' },
                    ]}
                    searchable={false}
                    emptyMessage="Aucune maintenance enregistrée"
                />
            </Card>
        </AuthenticatedLayout>
    );
}
