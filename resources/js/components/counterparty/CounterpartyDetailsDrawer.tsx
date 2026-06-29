import { type ReactNode } from 'react';
import Drawer from '@/components/ui/Drawer';
import Button from '@/components/ui/Button';
import DetailPanel, { DetailItem } from '@/components/ui/DetailPanel';
import { Pencil, Phone, Mail, MapPin, Globe } from 'lucide-react';
import type { Counterparty } from './types';

interface Props {
    entity: Counterparty;
    icon: ReactNode;
    canEdit: boolean;
    onEdit: () => void;
    onClose: () => void;
}

/**
 * Shared read-only details drawer for contact "counterparty" master-data
 * (Providers, Transporters). Identical detail layout across modules.
 */
export default function CounterpartyDetailsDrawer({ entity, icon, canEdit, onEdit, onClose }: Props) {
    return (
        <Drawer
            open
            onClose={onClose}
            size="md"
            icon={icon}
            title={entity.name}
            footer={canEdit ? <Button variant="secondary" icon={<Pencil size={15} />} onClick={onEdit}>Modifier</Button> : undefined}
        >
            <DetailPanel columns={1}>
                <DetailItem label="Nom" value={entity.name} />
                <DetailItem label="Téléphone" value={entity.phone} icon={<Phone size={13} />} />
                <DetailItem label="Email" value={entity.email} icon={<Mail size={13} />} />
                <DetailItem label="Adresse" value={entity.address} icon={<MapPin size={13} />} />
                <DetailItem
                    label="Site web"
                    icon={<Globe size={13} />}
                    value={entity.website
                        ? <a href={entity.website} target="_blank" rel="noopener noreferrer" className="text-[var(--color-primary)] hover:underline">{entity.website}</a>
                        : null}
                />
            </DetailPanel>
        </Drawer>
    );
}
