import { router } from '@inertiajs/react';
import Drawer from '@/components/ui/Drawer';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import DetailPanel, { DetailItem } from '@/components/ui/DetailPanel';
import { Truck as TruckIcon, Gauge, Fuel, Wrench, Pencil, ExternalLink, Play } from 'lucide-react';

export interface TruckRow {
    id: number;
    matricule: string;
    transporter: string | null;
    transporter_id: number | null;
    maintenance_type: string;
    km_maintenance_interval: number | null;
    target_rotations_per_week: number | null;
    is_active: boolean;
    is_available: boolean;
    total_kilometers: number;
    fleeti_last_fuel_level: number | null;
    level: string;
    remaining: number | string;
    unit: string;
}

interface Props {
    truck: TruckRow;
    canEdit: boolean;
    onEdit: () => void;
    onClose: () => void;
}

const fmt = (v: number | null | undefined) => (v == null ? '—' : v.toLocaleString('fr-FR', { maximumFractionDigits: 2 }));

/**
 * Quick-look details panel for a truck — opens in-place from the list. Covers the
 * glance-and-act case (status, counters, edit, toggle availability) and deep-links
 * to the full Truck Profile page for the heavy analytics (KPIs, fuel comparison,
 * transport & maintenance history).
 */
export default function TruckDetailsDrawer({ truck, canEdit, onEdit, onClose }: Props) {
    const maintBadge = truck.level === 'red'
        ? <Badge variant="danger">Urgent</Badge>
        : <Badge variant={truck.level === 'yellow' ? 'warning' : 'success'}>{truck.remaining} {truck.unit} restant{Number(truck.remaining) > 1 ? 's' : ''}</Badge>;

    const statusBadge = !truck.is_active
        ? <Badge variant="muted">Hors service</Badge>
        : truck.is_available
            ? <Badge variant="success">Disponible</Badge>
            : <Badge variant="danger">Indisponible</Badge>;

    const fuel = truck.fleeti_last_fuel_level;
    const fuelBadge = fuel == null
        ? <span className="text-[var(--color-text-muted)]">—</span>
        : <Badge variant={fuel < 30 ? 'danger' : fuel < 80 ? 'warning' : 'success'}>{fuel.toFixed(0)} L</Badge>;

    const toggleAvailability = () => {
        router.post(`/trucks/${truck.id}/toggle-availability`, {}, { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Drawer
            open
            onClose={onClose}
            icon={<TruckIcon size={18} className="text-[var(--color-primary)]" />}
            title={truck.matricule}
            footer={
                <>
                    <Button variant="ghost" icon={<ExternalLink size={15} />} onClick={() => router.visit(`/trucks/${truck.id}/show-page`)}>
                        Profil complet
                    </Button>
                    {canEdit && (
                        <Button icon={<Pencil size={15} />} onClick={onEdit}>Modifier</Button>
                    )}
                </>
            }
        >
            <div className="flex flex-wrap items-center gap-2">
                {statusBadge}
                <Badge variant="primary">{truck.maintenance_type}</Badge>
                {truck.transporter && <span className="text-sm text-[var(--color-text-muted)]">{truck.transporter}</span>}
            </div>

            <DetailPanel columns={2}>
                <DetailItem label="Compteur total" icon={<Gauge size={12} />} value={`${fmt(truck.total_kilometers)} km`} />
                <DetailItem label="Intervalle maintenance" icon={<Wrench size={12} />} value={truck.km_maintenance_interval ? `${fmt(truck.km_maintenance_interval)} km` : '—'} />
                <DetailItem label="Maintenance" icon={<Wrench size={12} />} value={maintBadge} />
                <DetailItem label="Carburant" icon={<Fuel size={12} />} value={fuelBadge} />
            </DetailPanel>

            {truck.is_active && (
                <Button
                    variant={truck.is_available ? 'secondary' : 'primary'}
                    icon={truck.is_available ? <Wrench size={15} /> : <Play size={15} />}
                    onClick={toggleAvailability}
                    className="w-full"
                >
                    {truck.is_available ? 'Marquer indisponible' : 'Marquer disponible'}
                </Button>
            )}
        </Drawer>
    );
}
