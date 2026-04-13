import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import Card from '@/components/ui/Card';
import DataTable from '@/components/ui/DataTable';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import LeafletMap from '@/components/map/LeafletMap';
import { Plus, MapPin, Edit, Trash2 } from 'lucide-react';

interface Place {
    id: number;
    code: string | null;
    name: string;
    type: string;
    latitude: number;
    longitude: number;
    radius_m: number;
    is_auto_detected: boolean;
    is_active: boolean;
    provider: { id: number; name: string } | null;
    notes: string | null;
}

interface Props {
    places: Place[];
}

const TYPE_LABELS: Record<string, string> = {
    base: 'Base',
    provider_site: 'Fournisseur',
    client_site: 'Client',
    fuel_station: 'Station',
    parking: 'Parking',
    unknown: 'Inconnu',
};

const TYPE_COLOR: Record<string, string> = {
    base: '#10b981',
    provider_site: '#3b82f6',
    client_site: '#8b5cf6',
    fuel_station: '#06b6d4',
    parking: '#64748b',
    unknown: '#ef4444',
};

export default function PlacesIndex({ places }: Props) {
    const [deleteId, setDeleteId] = useState<number | null>(null);

    const circles = places
        .filter((p) => p.is_active)
        .map((p) => ({
            id: p.id,
            latitude: p.latitude,
            longitude: p.longitude,
            radiusMeters: p.radius_m,
            color: TYPE_COLOR[p.type] ?? '#3b82f6',
            popup: (
                <div style={{ minWidth: 160 }}>
                    <strong>{p.name}</strong>
                    <div style={{ fontSize: 12 }}>
                        <div>{TYPE_LABELS[p.type] ?? p.type}</div>
                        <div>Rayon: {p.radius_m} m</div>
                    </div>
                </div>
            ),
        }));

    const handleDelete = (id: number) => {
        if (!confirm('Supprimer ce lieu ?')) return;
        router.post(`/logistics/places/${id}/destroy`);
        setDeleteId(null);
    };

    return (
        <AuthenticatedLayout title="Lieux / Géofences">
            <Head title="Lieux" />

            <div className="flex justify-between items-center mb-4">
                <p className="text-sm text-[var(--color-text-muted)]">
                    {places.length} lieu(x). Les bases auto-détectées sont rafraîchies chaque nuit depuis la télémétrie.
                </p>
                <Button icon={<Plus size={16} />} onClick={() => router.visit('/logistics/places/create')}>
                    Nouveau lieu
                </Button>
            </div>

            {places.length > 0 && (
                <Card padding={false} className="mb-5">
                    <div className="p-5">
                        <LeafletMap
                            height={400}
                            markers={places.map((p) => ({
                                id: p.id,
                                latitude: p.latitude,
                                longitude: p.longitude,
                                color: TYPE_COLOR[p.type] ?? '#3b82f6',
                                popup: <strong>{p.name}</strong>,
                            }))}
                            circles={circles}
                        />
                    </div>
                </Card>
            )}

            <Card padding={false}>
                <div className="p-5">
                    <DataTable
                        data={places}
                        columns={[
                            { key: 'name', label: 'Nom', render: (p) => (
                                <span className="flex items-center gap-2">
                                    <MapPin size={14} style={{ color: TYPE_COLOR[p.type] }} />
                                    <span>{p.name}</span>
                                    {p.is_auto_detected && <Badge variant="info">Auto</Badge>}
                                </span>
                            ) },
                            { key: 'type', label: 'Type', render: (p) => <Badge variant="primary">{TYPE_LABELS[p.type] ?? p.type}</Badge> },
                            { key: 'code', label: 'Code', hideOnMobile: true },
                            {
                                key: 'latitude',
                                label: 'Coordonnées',
                                hideOnMobile: true,
                                render: (p) => `${p.latitude.toFixed(5)}, ${p.longitude.toFixed(5)}`,
                            },
                            { key: 'radius_m', label: 'Rayon (m)', hideOnMobile: true },
                            {
                                key: 'is_active',
                                label: 'Actif',
                                render: (p) => <Badge variant={p.is_active ? 'success' : 'muted'}>{p.is_active ? 'Oui' : 'Non'}</Badge>,
                            },
                            {
                                key: 'actions',
                                label: 'Actions',
                                sortable: false,
                                render: (p) => (
                                    <div className="flex items-center gap-2">
                                        <a
                                            href={`/logistics/places/${p.id}/edit`}
                                            className="p-1.5 rounded hover:bg-[var(--color-surface-hover)] text-[var(--color-primary)]"
                                            title="Modifier"
                                        >
                                            <Edit size={14} />
                                        </a>
                                        <button
                                            onClick={() => handleDelete(p.id)}
                                            className="p-1.5 rounded hover:bg-[var(--color-surface-hover)] text-red-500"
                                            title="Supprimer"
                                        >
                                            <Trash2 size={14} />
                                        </button>
                                    </div>
                                ),
                            },
                        ]}
                        searchable
                        searchKeys={['name', 'code', 'type']}
                        emptyMessage="Aucun lieu enregistré. Le planificateur nocturne en créera automatiquement à partir de la télémétrie."
                    />
                </div>
            </Card>
        </AuthenticatedLayout>
    );
}
