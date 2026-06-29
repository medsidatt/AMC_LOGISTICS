import Drawer from '@/components/ui/Drawer';
import Button from '@/components/ui/Button';
import DetailPanel, { DetailItem } from '@/components/ui/DetailPanel';
import { Building2, Pencil, Phone, Mail, MapPin, Globe } from 'lucide-react';
import type { Provider } from '../types';

interface Props {
    provider: Provider;
    canEdit: boolean;
    onEdit: () => void;
    onClose: () => void;
}

/** Provider details (read-only) — reuses the platform Details Drawer standard. */
export default function ProviderDetailsDrawer({ provider, canEdit, onEdit, onClose }: Props) {
    return (
        <Drawer
            open
            onClose={onClose}
            size="md"
            icon={<Building2 size={18} className="text-[var(--color-primary)]" />}
            title={provider.name}
            footer={canEdit ? <Button variant="secondary" icon={<Pencil size={15} />} onClick={onEdit}>Modifier</Button> : undefined}
        >
            <DetailPanel columns={1}>
                <DetailItem label="Nom" value={provider.name} />
                <DetailItem label="Téléphone" value={provider.phone} icon={<Phone size={13} />} />
                <DetailItem label="Email" value={provider.email} icon={<Mail size={13} />} />
                <DetailItem label="Adresse" value={provider.address} icon={<MapPin size={13} />} />
                <DetailItem
                    label="Site web"
                    icon={<Globe size={13} />}
                    value={provider.website
                        ? <a href={provider.website} target="_blank" rel="noopener noreferrer" className="text-[var(--color-primary)] hover:underline">{provider.website}</a>
                        : null}
                />
            </DetailPanel>
        </Drawer>
    );
}
