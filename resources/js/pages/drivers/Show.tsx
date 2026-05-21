import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import DriverKpiSection, { type DriverKpi } from '@/components/driver/DriverKpiSection';
import { ArrowLeft, ShieldCheck, Power, PowerOff } from 'lucide-react';

interface Props {
    driver: {
        id: number;
        name: string;
        email: string | null;
        phone: string | null;
        address: string | null;
        is_active: boolean;
        whatsapp_opt_in_at: string | null;
        created_at: string | null;
        updated_at: string | null;
    };
    kpi: DriverKpi;
    filter: { from: string; to: string; preset: 'day' | 'week' | 'month' | 'year' | 'custom' };
}

export default function DriversShow({ driver, kpi, filter }: Props) {
    const fields = [
        ['Nom', driver.name],
        ['Email', driver.email],
        ['Téléphone', driver.phone],
        ['Adresse', driver.address],
        ['Consentement WhatsApp', driver.whatsapp_opt_in_at
            ? `Oui (depuis le ${driver.whatsapp_opt_in_at})`
            : 'Non'],
        ['Créé le', driver.created_at],
        ['Modifié le', driver.updated_at],
    ];

    return (
        <AuthenticatedLayout title={driver.name}>
            <Head title={driver.name} />

            <div className="mb-4 flex items-center justify-between">
                <Button variant="ghost" icon={<ArrowLeft size={16} />} onClick={() => window.history.back()}>
                    Retour
                </Button>
                <div className="flex items-center gap-2">
                    <Button
                        variant={driver.is_active ? 'secondary' : 'primary'}
                        icon={driver.is_active ? <PowerOff size={14} /> : <Power size={14} />}
                        onClick={() => {
                            const msg = driver.is_active
                                ? `Désactiver ${driver.name} ?`
                                : `Activer ${driver.name} ?`;
                            if (confirm(msg)) {
                                router.post(`/drivers/${driver.id}/toggle-active`, {}, { preserveScroll: true });
                            }
                        }}
                    >
                        {driver.is_active ? 'Désactiver' : 'Activer'}
                    </Button>
                    <Link
                        href={`/drivers/${driver.id}/discipline`}
                        className="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm bg-[var(--color-surface)] hover:bg-[var(--color-surface-hover)] border border-[var(--color-border)] text-[var(--color-text)]"
                    >
                        <ShieldCheck size={14} />
                        Discipline
                    </Link>
                </div>
            </div>

            <Card className="mb-6">
                <div className="flex items-center justify-between mb-4">
                    <h3 className="text-lg font-semibold text-[var(--color-text)]">Informations conducteur</h3>
                    <Badge variant={driver.is_active ? 'success' : 'muted'}>{driver.is_active ? 'Actif' : 'Inactif'}</Badge>
                </div>
                <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    {fields.map(([label, value]) => (
                        <div key={label as string}>
                            <p className="text-xs text-[var(--color-text-muted)] uppercase">{label}</p>
                            <p className="text-sm text-[var(--color-text)] mt-0.5">{value || '-'}</p>
                        </div>
                    ))}
                </div>
            </Card>

            <DriverKpiSection driverId={driver.id} kpi={kpi} filter={filter} />
        </AuthenticatedLayout>
    );
}
