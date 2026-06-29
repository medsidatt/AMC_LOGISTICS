import { router } from '@inertiajs/react';
import Drawer from '@/components/ui/Drawer';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import DetailPanel, { DetailItem } from '@/components/ui/DetailPanel';
import { User, Pencil, Power, PowerOff, ShieldCheck, ExternalLink, Mail, Phone, MapPin } from 'lucide-react';

export interface DriverRow {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    is_active: boolean;
    created_at: string | null;
}

interface Props {
    driver: DriverRow;
    canEdit: boolean;
    onEdit: () => void;
    onClose: () => void;
}

/**
 * Quick-look driver details — opens in-place from the list. Covers the glance-and-act
 * case (contact info, activate/deactivate, discipline) and deep-links to the full
 * Driver Profile page for the KPI analytics.
 */
export default function DriverDetailsDrawer({ driver, canEdit, onEdit, onClose }: Props) {
    const toggle = () => {
        const msg = driver.is_active ? `Désactiver ${driver.name} ?` : `Activer ${driver.name} ?`;
        if (confirm(msg)) router.post(`/drivers/${driver.id}/toggle-active`, {}, { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Drawer
            open
            onClose={onClose}
            icon={<User size={18} className="text-[var(--color-primary)]" />}
            title={driver.name}
            footer={
                <>
                    <Button variant="ghost" icon={<ExternalLink size={15} />} onClick={() => router.visit(`/drivers/${driver.id}/show-page`)}>
                        Profil complet
                    </Button>
                    {canEdit && <Button icon={<Pencil size={15} />} onClick={onEdit}>Modifier</Button>}
                </>
            }
        >
            <div className="flex flex-wrap items-center gap-2">
                <Badge variant={driver.is_active ? 'success' : 'muted'}>{driver.is_active ? 'Actif' : 'Inactif'}</Badge>
            </div>

            <DetailPanel columns={2}>
                <DetailItem label="Email" icon={<Mail size={12} />} value={driver.email} />
                <DetailItem label="Téléphone" icon={<Phone size={12} />} value={driver.phone} />
                <DetailItem label="Adresse" icon={<MapPin size={12} />} value={driver.address} />
                <DetailItem label="Créé le" value={driver.created_at} />
            </DetailPanel>

            <div className="flex flex-wrap gap-2">
                {canEdit && (
                    <Button
                        variant={driver.is_active ? 'secondary' : 'primary'}
                        icon={driver.is_active ? <PowerOff size={15} /> : <Power size={15} />}
                        onClick={toggle}
                    >
                        {driver.is_active ? 'Désactiver' : 'Activer'}
                    </Button>
                )}
                <Button variant="secondary" icon={<ShieldCheck size={15} />} onClick={() => router.visit(`/drivers/${driver.id}/discipline`)}>
                    Discipline
                </Button>
            </div>
        </Drawer>
    );
}
